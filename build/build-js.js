const fs = require('fs');
const path = require('path');
const { minify } = require('terser');
const config = require('./config');

const ROOT_DIR = path.join(__dirname, '..');
const ASSETS_JS_DIR = path.join(ROOT_DIR, 'assets', 'js');

// Use files from config
const jsFiles = config.jsFiles;

async function buildJS() {
  console.log('🔨 Building JavaScript bundle...\n');

  try {
    // Read all JavaScript files
    let combinedJS = '';
    
    for (const file of jsFiles) {
      const filePath = path.join(ASSETS_JS_DIR, file);
      console.log(`📄 Reading ${file}...`);
      
      if (!fs.existsSync(filePath)) {
        console.error(`❌ Error: ${file} not found at ${filePath}`);
        process.exit(1);
      }
      
      const content = fs.readFileSync(filePath, 'utf8');
      combinedJS += `\n// ========== ${file} ==========\n`;
      combinedJS += content;
      combinedJS += '\n';
    }

    console.log('\n🗜️  Minifying combined JavaScript...');
    
    // Minify the combined JavaScript
    const result = await minify(combinedJS, {
      compress: {
        dead_code: true,
        drop_console: false, // Keep console.log/warn/error in production for debugging. Set to true to remove all console statements.
        drop_debugger: true,
        pure_funcs: []
      },
      mangle: {
        toplevel: false // Don't mangle top-level names
      },
      format: {
        comments: false // Remove comments
      },
      sourceMap: false
    });

    if (result.error) {
      console.error('❌ Minification error:', result.error);
      process.exit(1);
    }

    // Write the minified file
    const outputPath = path.join(ASSETS_JS_DIR, config.output.js);
    fs.writeFileSync(outputPath, result.code, 'utf8');

    // Get file sizes
    const originalSize = Buffer.byteLength(combinedJS, 'utf8');
    const minifiedSize = Buffer.byteLength(result.code, 'utf8');
    const reduction = ((1 - minifiedSize / originalSize) * 100).toFixed(2);

    console.log('\n✅ JavaScript bundle created successfully!');
    console.log(`📊 Original size: ${(originalSize / 1024).toFixed(2)} KB`);
    console.log(`📊 Minified size: ${(minifiedSize / 1024).toFixed(2)} KB`);
    console.log(`📉 Size reduction: ${reduction}%`);
    console.log(`📁 Output: ${outputPath}\n`);

  } catch (error) {
    console.error('❌ Build error:', error);
    process.exit(1);
  }
}

buildJS();
