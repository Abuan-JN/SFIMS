# SFIMS Credits Page - Update Guide

## Overview
The credits page (`credits.php`) displays team member information in a professional, responsive grid layout with interactive hover effects.

## File Structure
```
sfims/
├── credits.php                 # Main credits page
├── assets/
│   └── img/
│       └── team/              # Team member photos directory
│           ├── member1.jpg
│           ├── member2.jpg
│           └── ...
└── docs/
    └── CREDITS_GUIDE.md       # This file
```

## How to Update Team Members

### 1. Adding/Updating Team Member Photos

1. Prepare high-quality portrait photos (recommended: 400x400 pixels, square format)
2. Name the files consistently: `member1.jpg`, `member2.jpg`, etc.
3. Place photos in `assets/img/team/` directory
4. Supported formats: JPG, PNG, WebP

**Photo Guidelines:**
- Professional headshots with clear facial features
- Consistent lighting and background
- Square aspect ratio (1:1)
- File size: 100KB - 500KB for optimal loading

### 2. Updating Team Member Information

Edit the `credits.php` file and locate the team member cards. Each card follows this structure:

```php
<div class="team-card">
    <div class="team-photo">
        <img src="<?php echo BASE_URL; ?>assets/img/team/memberX.jpg" 
             alt="Full Name" 
             onerror="this.src='https://ui-avatars.com/api/?name=Full+Name&size=150&background=4ade80&color=1e451e&bold=true'">
    </div>
    <h3 class="team-name">Full Name</h3>
    <p class="team-role">Role/Title</p>
    <p class="team-bio">Brief description of responsibilities and expertise.</p>
    <div class="team-social">
        <a href="#" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
        <a href="#" title="GitHub"><i class="bi bi-github"></i></a>
        <a href="#" title="Email"><i class="bi bi-envelope-fill"></i></a>
    </div>
</div>
```

### 3. Fields to Update

| Field | Description | Example |
|-------|-------------|---------|
| `memberX.jpg` | Photo filename | `member1.jpg` |
| `alt="Full Name"` | Alt text for accessibility | `alt="Juan Dela Cruz"` |
| `onerror` | Fallback avatar URL | Update name parameter |
| `team-name` | Full name | `Juan Dela Cruz` |
| `team-role` | Job title/role | `Project Manager` |
| `team-bio` | Brief description | `Leading the SFIMS development...` |
| `team-social` | Social media links | Update `href` attributes |

### 4. Adding Social Media Links

Update the `href` attributes in the social media section:

```php
<div class="team-social">
    <a href="https://linkedin.com/in/username" title="LinkedIn"><i class="bi bi-linkedin"></i></a>
    <a href="https://github.com/username" title="GitHub"><i class="bi bi-github"></i></a>
    <a href="mailto:email@example.com" title="Email"><i class="bi bi-envelope-fill"></i></a>
</div>
```

### 5. Adding/Removing Team Members

**To add a new team member:**
1. Copy an existing `team-card` div
2. Update all fields (photo, name, role, bio, social links)
3. Place in the appropriate position within the `team-grid`

**To remove a team member:**
1. Delete the entire `team-card` div for that person

### 6. Customizing the Design

#### Changing Colors
The page uses CSS variables defined in the `<style>` section:

```css
:root {
    --sfims-green: #1e451e;      /* Primary green */
    --sfims-accent: #4ade80;      /* Accent green */
    --sfims-card-bg: #ffffff;     /* Card background */
    --sfims-border: #e5e7eb;      /* Border color */
    --sfims-text: #1f2937;        /* Text color */
}
```

#### Adjusting Grid Layout
Modify the `grid-template-columns` property:

```css
.team-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    /* Change 280px to adjust minimum card width */
}
```

#### Changing Card Size
Adjust the `.team-card` padding and `.team-photo` dimensions:

```css
.team-card {
    padding: 30px;  /* Adjust card padding */
}

.team-photo {
    width: 150px;   /* Adjust photo size */
    height: 150px;
}
```

### 7. Responsive Breakpoints

The page includes responsive design for:
- **Desktop**: 3-4 cards per row
- **Tablet**: 2 cards per row
- **Mobile**: 1 card per row

Breakpoints are defined at:
- `max-width: 768px` (tablet)
- `max-width: 480px` (mobile)

### 8. Browser Compatibility

Tested and compatible with:
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### 9. Performance Tips

1. **Optimize images**: Compress photos to 100-500KB
2. **Use WebP format**: Better compression than JPG/PNG
3. **Lazy loading**: Already implemented for images
4. **CDN**: Consider using a CDN for images in production

### 10. Troubleshooting

**Photos not displaying:**
- Check file path in `src` attribute
- Verify file exists in `assets/img/team/` directory
- Check file permissions (should be readable)
- Verify file extension matches (case-sensitive on Linux)

**Styling issues:**
- Clear browser cache
- Check CSS variable definitions
- Verify Bootstrap 5 is loaded

**Responsive issues:**
- Test on actual devices
- Check viewport meta tag
- Verify CSS media queries

## Quick Reference

### Default Team Structure (6 members)
1. Project Manager
2. Lead Developer
3. Backend Developer
4. Frontend Developer
5. Database Administrator
6. Quality Assurance

### File Naming Convention
- Photos: `member1.jpg`, `member2.jpg`, etc.
- Fallback: Uses `ui-avatars.com` API for placeholder avatars

### Color Scheme
- Primary: `#1e451e` (dark green)
- Accent: `#4ade80` (light green)
- Background: `#f9fafb` (light gray)
- Text: `#1f2937` (dark gray)

---

**Last Updated:** 2026-03-27
**Maintainer:** SFIMS Development Team
