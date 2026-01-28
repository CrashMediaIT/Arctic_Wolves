# Arctic Wolves - Style Guide Documentation

## Overview

This document describes the unified style guide for the Arctic Wolves dashboard. The style guide is based on the **Upcoming Sessions** and **Bookings** views, which serve as the reference implementation for all UI components.

## Files

- **`css/style-guide.css`** - The authoritative stylesheet containing all design tokens and component styles
- **`docs-style-guide.html`** - A visual demonstration of all components styled according to the guide

## Design Tokens

### Colors

| Variable | Value | Usage |
|----------|-------|-------|
| `--bg-main` | `#0A0A0F` | Main background color |
| `--bg-secondary` | `#13131A` | Secondary background |
| `--bg-card` | `#16161F` | Card and panel backgrounds |
| `--primary` | `#6B46C1` | Primary brand color (Deep Purple) |
| `--primary-hover` | `#7C3AED` | Primary hover state |
| `--primary-light` | `#8B5CF6` | Lighter purple accent |
| `--text-white` | `#FFFFFF` | Primary text color |
| `--text-secondary` | `#A8A8B8` | Secondary/muted text |
| `--text-muted` | `#6B6B7B` | Very muted text |
| `--border` | `#2D2D3F` | Default border color |
| `--success` | `#10B981` | Success state |
| `--warning` | `#F59E0B` | Warning state |
| `--error` | `#EF4444` | Error/danger state |

### Typography

- **Font Family:** Inter, system-ui, sans-serif
- **Font Sizes:** 11px (xs) to 28px (3xl)
- **Font Weights:** 400 (normal) to 900 (black)

## Component Reference

### 1. Page Header

```html
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-alt"></i> Page Title
    </h1>
    <p class="page-description">Description text here</p>
</div>
```

### 2. Buttons

**Primary Button:**
```html
<button class="btn btn-primary">
    <i class="fas fa-plus"></i> Primary Action
</button>
```

**Secondary Button:**
```html
<button class="btn btn-secondary">
    <i class="fas fa-times"></i> Secondary Action
</button>
```

**Button Variants:**
- `.btn-primary` - Deep purple, main actions
- `.btn-secondary` - Outlined/ghost style
- `.btn-success` - Green, successful actions
- `.btn-danger` - Red, destructive actions
- `.btn-warning` - Amber, cautionary actions
- `.btn-outline` - Transparent with border

**Button Sizes:**
- `.btn-sm` - Small (36px height)
- `.btn-lg` - Large (48px height)

### 3. Filter Box

```html
<div class="filter-box">
    <div class="filter-box-header">
        <i class="fas fa-filter"></i> Filter Sessions
    </div>
    <div class="filter-box-content">
        <form class="filter-row">
            <div class="filter-field">
                <label>Field Label</label>
                <select class="form-select">...</select>
            </div>
            <div class="filter-field filter-actions">
                <button class="btn btn-primary">Apply</button>
                <button class="btn btn-secondary">Clear</button>
            </div>
        </form>
    </div>
</div>
```

### 4. Action Bar

```html
<div class="action-bar">
    <div class="results-info">
        <span>12 items found</span>
    </div>
    <div class="view-controls">
        <div class="view-toggle">
            <button class="view-btn active"><i class="fas fa-list"></i></button>
            <button class="view-btn"><i class="fas fa-calendar"></i></button>
        </div>
        <button class="btn btn-primary"><i class="fas fa-plus"></i> Add New</button>
    </div>
</div>
```

### 5. Session List Card

```html
<div class="session-list-card">
    <div class="session-date-column">
        <div class="date-badge">
            <span class="date-month">JAN</span>
            <span class="date-day">28</span>
            <span class="date-weekday">Wed</span>
        </div>
        <span class="session-time">10:00 AM</span>
    </div>
    <div class="session-details-column">
        <h4 class="session-title">Session Title</h4>
        <div class="session-meta">
            <span class="meta-item"><i class="fas fa-user-tie"></i> Coach Name</span>
            <span class="meta-item"><i class="fas fa-map-marker-alt"></i> Location</span>
        </div>
    </div>
    <div class="session-action-column">
        <div class="spots-indicator">
            <span class="spots-number">5</span>
            <span class="spots-text">spots left</span>
        </div>
        <button class="btn-register"><i class="fas fa-plus-circle"></i> Register</button>
    </div>
</div>
```

### 6. Page Tabs

Used for parent pages with sub-views (Sessions, Video, Health, Drills, etc.):

**Standard Page Tabs:**
```html
<div class="page-tabs">
    <a href="?page=sub_page1" class="page-tab active">
        <i class="fas fa-clock"></i> Tab 1
    </a>
    <a href="?page=sub_page2" class="page-tab">
        <i class="fas fa-calendar"></i> Tab 2
    </a>
</div>
<div class="page-tab-content">
    <!-- Sub-page content here -->
</div>
```

**Page Tabs with Action Button:**
```html
<div class="page-tabs-wrapper">
    <div class="page-tabs">
        <a href="?page=sub_page1" class="page-tab active">
            <i class="fas fa-film"></i> Tab 1
        </a>
        <a href="?page=sub_page2" class="page-tab">
            <i class="fas fa-comments"></i> Tab 2
        </a>
    </div>
    <div class="page-tabs-action">
        <button class="btn btn-primary"><i class="fas fa-plus"></i> Action</button>
    </div>
</div>
<div class="page-tab-content">
    <!-- Sub-page content here -->
</div>
```

### 7. Cards

```html
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-icon"></i> Card Title</h3>
    </div>
    <div class="card-body">
        Card content here
    </div>
    <div class="card-footer">
        Footer content
    </div>
</div>
```

### 8. Forms

```html
<div class="form-group">
    <label>Field Label <span class="required">*</span></label>
    <input type="text" class="form-input" placeholder="Placeholder">
</div>

<div class="form-row">
    <div class="form-group">
        <label>Field 1</label>
        <select class="form-input">...</select>
    </div>
    <div class="form-group">
        <label>Field 2</label>
        <input type="date" class="form-input">
    </div>
</div>
```

### 9. Badges

```html
<span class="badge badge-primary">Primary</span>
<span class="badge badge-success">Success</span>
<span class="badge badge-warning">Warning</span>
<span class="badge badge-danger">Danger</span>
```

### 10. Alerts

```html
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span>Success message here</span>
</div>

<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <span>Error message here</span>
</div>
```

### 11. Empty States

```html
<div class="empty-state-card">
    <i class="fas fa-calendar-times"></i>
    <h4>No Data Found</h4>
    <p>Description of what the user can do</p>
</div>
```

## Usage Guidelines

1. **Always use CSS variables** for colors, spacing, and typography to ensure consistency
2. **Use the standard component classes** rather than creating custom styles
3. **Page-specific styles** should be placed in inline `<style>` blocks within the view files
4. **Button actions** should use `data-action` attributes for JavaScript handlers
5. **Icons** should use Font Awesome classes with the purple primary color for accents

## Responsive Behavior

The style guide includes responsive breakpoints:
- **1024px** - Tablet breakpoint
- **768px** - Mobile breakpoint
- **480px** - Small mobile breakpoint

All components automatically adapt to these breakpoints.
