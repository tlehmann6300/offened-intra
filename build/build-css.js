const fs = require('fs');
const path = require('path');
const postcss = require('postcss');
const cssnano = require('cssnano');
const { PurgeCSS } = require('purgecss');
const config = require('./config');

const ROOT_DIR = path.join(__dirname, '..');
const ASSETS_CSS_DIR = path.join(ROOT_DIR, 'assets', 'css');

// Use files from config
const cssFiles = config.cssFiles;

// Content files for PurgeCSS to analyze (using glob patterns)
const contentFiles = [
  path.join(ROOT_DIR, 'templates') + '/**/*.php',
  path.join(ROOT_DIR, 'assets', 'js') + '/*.js',
  path.join(ROOT_DIR, 'index.php')
];

async function buildCSS() {
  console.log('🔨 Building CSS files...\n');

  try {
    for (const { input, output } of cssFiles) {
      const inputPath = path.join(ASSETS_CSS_DIR, input);
      const outputPath = path.join(ASSETS_CSS_DIR, output);
      
      console.log(`📄 Processing ${input}...`);
      
      if (!fs.existsSync(inputPath)) {
        console.error(`❌ Error: ${input} not found at ${inputPath}`);
        process.exit(1);
      }
      
      // Read the CSS file
      const css = fs.readFileSync(inputPath, 'utf8');
      const originalSize = Buffer.byteLength(css, 'utf8');

      // Step 1: Run PurgeCSS to remove unused styles (only for theme.css)
      let processedCSS = css;
      
      if (input === 'theme.css') {
        console.log(`  🧹 Removing unused styles from ${input}...`);
        
        const purgeCSSResult = await new PurgeCSS().purge({
          content: contentFiles,
          css: [{ raw: css }],
          safelist: {
            standard: [
              /^aos-/,           // Animate on scroll
              /^fa-/,            // Font Awesome
              /^btn-/,           // Bootstrap buttons
              /^dropdown-/,      // Bootstrap dropdowns
              /^modal-/,         // Bootstrap modals
              /^navbar-/,        // Bootstrap navbar
              /^badge-/,         // Bootstrap badges
              /^alert-/,         // Bootstrap alerts
              /^card-/,          // Bootstrap cards
              /^bg-/,            // Background colors
              /^text-/,          // Text colors
              /^border-/,        // Borders
              /^edit-mode/,      // Edit mode classes
              /^cookie-/,        // Cookie banner
              /^notification-/,  // Notifications
              /show/,            // Show/hide utilities
              /active/,          // Active states
              /collapsed/,       // Collapsed states
              /^search-/,        // Search components
            ],
            deep: [/^data-/],    // Keep data attributes
            greedy: []
          }
        });
        
        processedCSS = purgeCSSResult[0].css;
        const afterPurgeSize = Buffer.byteLength(processedCSS, 'utf8');
        const purgeReduction = ((1 - afterPurgeSize / originalSize) * 100).toFixed(2);
        console.log(`  📉 PurgeCSS reduction: ${purgeReduction}%`);
      }

      // Step 2: Minify with cssnano
      console.log(`  🗜️  Minifying ${input}...`);
      
      const result = await postcss([
        cssnano({
          preset: ['default', {
            discardComments: { removeAll: true },
            normalizeWhitespace: true,
            colormin: true,
            minifyFontValues: true,
            minifySelectors: true
          }]
        })
      ]).process(processedCSS, { from: inputPath, to: outputPath });

      // Write the minified file
      fs.writeFileSync(outputPath, result.css, 'utf8');

      // Get file sizes
      const minifiedSize = Buffer.byteLength(result.css, 'utf8');
      const totalReduction = ((1 - minifiedSize / originalSize) * 100).toFixed(2);

      console.log(`  📊 Original size: ${(originalSize / 1024).toFixed(2)} KB`);
      console.log(`  📊 Minified size: ${(minifiedSize / 1024).toFixed(2)} KB`);
      console.log(`  📉 Total reduction: ${totalReduction}%`);
      console.log(`  ✅ Created ${output}\n`);
    }

    console.log('✅ All CSS files processed successfully!\n');

  } catch (error) {
    console.error('❌ Build error:', error);
    process.exit(1);
  }
}

buildCSS();
