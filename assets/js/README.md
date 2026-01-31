# JavaScript Modular Structure

## Overview

The JavaScript codebase has been refactored into modular files for better maintainability and organization. Each module focuses on a specific functional area.

## Module Structure

### 1. **core.js** (Base Module - 1095 lines)
**Purpose:** Global functions, CSRF protection, notifications, and UI initialization

**Key Features:**
- CSRF token management (`getCsrfToken`, `addCsrfHeader`)
- Secure fetch wrapper (`window.secureFetch`)
- Toast notifications (`showToast`)
- Button state management (`toggleButtonState`, `toggleButtonLoading`)
- Global image error handler
- Cookie banner functionality
- Storage helpers (`getStorageItem`, `setStorageItem`)
- Scroll animations and AOS initialization
- Form validation
- Language switching
- Notification bell
- Quick add location functionality

**Dependencies:** None (base module)

**Initialization:** Automatic on `DOMContentLoaded`

---

### 2. **inventory.js** (Inventory Management - 1338 lines)
**Purpose:** Complete inventory management system

**Key Features:**
- Image preview functionality
- Live AJAX search with multi-filter support
- Skeleton loaders for inventory items
- Inventory card rendering
- CRUD operations (create, read, update, delete)
- Quantity adjustment with comment tracking
- Drag-and-drop file upload
- Category and location filtering
- Event delegation for dynamic content
- Back-to-top button

**Dependencies:** Requires `core.js` (uses CSRF, showToast, debounce, etc.)

**Initialization:** Automatic when `#inventoryForm` element is present

---

### 3. **events.js** (Event Management - 125 lines)
**Purpose:** Event countdown and RSVP functionality

**Key Features:**
- Event countdown timer with days/hours/minutes/seconds
- Auto-updates every second
- Handles past events gracefully

**Dependencies:** Requires `core.js` (uses showToast)

**Initialization:** Automatic when `#event-countdown` element is present

---

### 4. **alumni.js** (Alumni Database - 786 lines)
**Purpose:** Alumni profile management and search

**Key Features:**
- Live AJAX search for alumni profiles
- Skeleton loaders for alumni cards
- Profile edit modal
- Graduation year filtering
- Edit mode toggle
- Profile data population and updates
- CSRF-protected profile updates

**Dependencies:** Requires `core.js` (uses CSRF, showToast, debounce, etc.)

**Initialization:** Automatic when alumni-related elements are present

---

## Build Process

All modules are concatenated and minified into `app.min.js` using the build script:

```bash
npm run build:js
```

### Build Configuration

See `build/config.js` for the module order:
1. core.js (must be first - provides shared utilities)
2. inventory.js
3. events.js
4. alumni.js
5. Other utility modules (navbar, navigation, news, etc.)

### Build Output
- **Original size:** ~184 KB
- **Minified size:** ~90 KB
- **Reduction:** ~51%

---

## Conditional Loading

While all code is bundled in `app.min.js`, each module initializes conditionally:

- **Core functions** → Initialize on every page
- **Inventory functions** → Only if `#inventoryForm` exists
- **Event functions** → Only if `#event-countdown` exists
- **Alumni functions** → Only if alumni DOM elements exist

This ensures efficient execution without unnecessary overhead.

---

## Development Guidelines

### Adding New Functionality

1. **Determine the appropriate module:**
   - Global/shared functionality → `core.js`
   - Inventory-related → `inventory.js`
   - Event-related → `events.js`
   - Alumni-related → `alumni.js`

2. **Follow naming conventions:**
   - Use descriptive function names
   - Prefix init functions with `init` (e.g., `initInventoryManagement`)
   - Use camelCase for functions and variables

3. **Add conditional initialization:**
   - Check for specific DOM elements before initializing
   - Use `document.getElementById()` or `document.querySelector()` checks

4. **Rebuild after changes:**
   ```bash
   npm run build:js
   ```

### Testing

After modifying modules:

1. **Syntax check:**
   ```bash
   node -c assets/js/core.js
   node -c assets/js/inventory.js
   node -c assets/js/events.js
   node -c assets/js/alumni.js
   ```

2. **Build test:**
   ```bash
   npm run build:js
   ```

3. **Browser test:**
   - Test the specific page affected by your changes
   - Check browser console for errors
   - Verify functionality works as expected

---

## Dependencies Between Modules

```
core.js (no dependencies)
   ↓
   ├── inventory.js (depends on core.js)
   ├── events.js (depends on core.js)
   └── alumni.js (depends on core.js)
```

**Important:** `core.js` must always be loaded first in the build configuration.

---

## File Locations

- **Source modules:** `/assets/js/`
- **Minified bundle:** `/assets/js/app.min.js`
- **Build script:** `/build/build-js.js`
- **Build config:** `/build/config.js`

---

## Migration from main.js

The original `main.js` (3782 lines, 139 KB) has been split into:

| Module | Lines | Size | Content |
|--------|-------|------|---------|
| core.js | 1095 | 36 KB | Core utilities & initialization |
| inventory.js | 1338 | 51 KB | Inventory management |
| events.js | 125 | 5 KB | Event countdown |
| alumni.js | 786 | 36 KB | Alumni database |
| **Total** | **3344** | **128 KB** | All modular code |

**Note:** The original `main.js` is kept for reference but is no longer loaded in templates.

---

## Benefits of Modular Structure

1. **Better Organization** - Related code is grouped together
2. **Easier Maintenance** - Changes are isolated to specific modules
3. **Improved Readability** - Smaller, focused files are easier to understand
4. **Reusability** - Modules can be tested and debugged independently
5. **Conditional Execution** - Only runs code when needed
6. **Team Collaboration** - Multiple developers can work on different modules

---

Last Updated: 2026-01-31
