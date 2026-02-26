# CIG Admin Dashboard - Project Structure

## 📁 Folder Organization

The CIG Admin Dashboard has been organized into a clean, modular structure for better maintainability and scalability.

```
CIG/
├── pages/              # HTML page files
│   ├── index.html      # Main dashboard landing page
│   ├── login.html      # Login page
│   ├── dashboard.html  # Dashboard view
│   ├── submissions.html # Submissions management
│   ├── review.html     # Review & approval page
│   ├── archive.html    # Document archive page
│   ├── organizations.html # Organizations management
│   └── reports.html    # Reports view
│
├── css/                # Stylesheets
│   ├── style.css       # Global styles
│   ├── navbar.css      # Navigation bar styles
│   ├── login.css       # Login page styles
│   ├── dashboard.css   # Dashboard styles
│   ├── submissions.css # Submissions styles
│   ├── review.css      # Review page styles
│   ├── archive.css     # Archive page styles
│   ├── organizations.css # Organizations styles
│   └── reports.css     # Reports styles
│
├── js/                 # JavaScript files
│   ├── script.js       # Global utilities
│   ├── navbar.js       # Navigation logic
│   ├── index.js        # Index page scripts
│   ├── login.js        # Login page scripts
│   ├── dashboard.js    # Dashboard scripts
│   ├── submissions.js  # Submissions scripts
│   ├── review.js       # Review page scripts
│   ├── archive.js      # Archive page scripts
│   ├── organizations.js # Organizations scripts
│   └── reports.js      # Reports scripts
│
├── assets/             # Images and media
│   ├── cigorig.png     # CIG logo
│   ├── osas2.png       # OSAS logo
│   ├── plsplogo.png    # PLSP logo
│   ├── bg1.png         # Background image 1
│   ├── bg3.png         # Background image 3
│   ├── bglast.png      # Background image
│   ├── ciglogo.png     # Alternative CIG logo
│   ├── plspfront.png   # PLSP front image
│   ├── 2wht.jpg        # White background image
│   └── whitebg.jpg     # White background image
│
└── .vscode/            # VS Code configuration
    └── launch.json

```

## 🔗 File Connections

All HTML files maintain their connections through relative path references:

### HTML Navigation Links
- All page-to-page links remain unchanged (e.g., `href="index.html"`, `href="dashboard.html"`)
- These work because files are in the same `pages/` directory

### CSS References (Updated)
- From: `href="style.css"` 
- To: `href="../css/style.css"`

### JavaScript References (Updated)
- From: `<script src="navbar.js"></script>`
- To: `<script src="../js/navbar.js"></script>`

### Image References (Updated)
- From: `<img src="cigorig.png">`
- To: `<img src="../assets/cigorig.png">`

## 🚀 How to Use

1. **Start with the login page** (if authentication is required):
   ```
   open pages/login.html
   ```

2. **Or go directly to dashboard**:
   ```
   open pages/index.html
   ```

3. All pages are served from the `pages/` folder and automatically load:
   - Stylesheets from `../css/`
   - Scripts from `../js/`
   - Images from `../assets/`

## ✨ Benefits of This Structure

- **Separation of Concerns**: HTML, CSS, and JS are organized separately
- **Easy Maintenance**: Find and update styles or scripts quickly
- **Scalability**: Easy to add new pages or components
- **Asset Management**: All media centralized in one location
- **Clean Root**: Root directory is clutter-free

## 📝 Notes

- All relative paths are updated and functional
- No broken links - all connections are maintained
- This structure follows common web development best practices
- Easy to convert to a build system (webpack, vite, etc.) in the future
