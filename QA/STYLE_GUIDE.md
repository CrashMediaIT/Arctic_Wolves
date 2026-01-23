# Crash Hockey - Style Guide

**Version**: 1.1  
**Last Updated**: January 23, 2026

---

## Color Palette

### Primary Colors
```css
--primary:          #6B46C1    /* Deep Purple - Primary brand color */
--primary-hover:    #7C3AED    /* Violet - Hover state */
--primary-light:    #8B5CF6    /* Light Purple - Accents */
--primary-dark:     #5B21B6    /* Dark Purple - Active state */
```

### Background Colors
```css
--bg:               #0A0A0F    /* Deep Black - Main background */
--bg-secondary:     #13131A    /* Dark Gray - Secondary background */
--sidebar:          #0D0D14    /* Darker - Sidebar background */
--card-bg:          #16161F    /* Card/Panel background */
```

### Border & Divider Colors
```css
--border:           #2D2D3F    /* Border color */
--border-light:     #3A3A4F    /* Light border */
--border-dark:      #1A1A25    /* Dark border */
```

### Text Colors
```css
--text-primary:     #FFFFFF    /* White - Primary text */
--text-secondary:   #A8A8B8    /* Light Gray - Secondary text */
--text-muted:       #6B6B7B    /* Muted Gray - Disabled/muted text */
--text-inverse:     #0A0A0F    /* Black - Text on light backgrounds */
```

### Status Colors
```css
--success:          #10B981    /* Green - Success states */
--warning:          #F59E0B    /* Amber - Warning states */
--error:            #EF4444    /* Red - Error states */
--info:             #3B82F6    /* Blue - Info states */
```

---

## Typography

### Font Family
```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

### Font Sizes
```css
--font-xs:    11px    /* Extra small text */
--font-sm:    13px    /* Small text */
--font-base:  14px    /* Body text */
--font-md:    16px    /* Medium text */
--font-lg:    18px    /* Large text */
--font-xl:    22px    /* Extra large text */
--font-2xl:   28px    /* Heading text */
--font-3xl:   36px    /* Large heading */
```

### Font Weights
```css
--font-normal:  400
--font-medium:  500
--font-semibold: 600
--font-bold:    700
--font-black:   900
```

---

## Component Standards

### Input Fields
```css
height: 45px;
padding: 0 16px;
background: var(--bg);
border: 1px solid var(--border);
border-radius: 8px;
color: var(--text-primary);
font-size: 14px;
font-weight: 400;
```

### Buttons
```css
height: 45px;
padding: 0 24px;
background: var(--primary);
color: white;
border: none;
border-radius: 8px;
font-size: 14px;
font-weight: 700;
font-family: 'Inter', sans-serif;
cursor: pointer;
transition: all 0.3s ease;
```

**Button Data Attributes (Required for Functionality):**
- Navigation: `data-action="view"` + `data-page="page-name"`
- Add/Create: `data-action="add"` + `data-modal="modal-id"` OR `data-page="page-name"`
- Edit: `data-action="edit"` + `data-id="123"` + `data-modal="edit-modal"`
- Delete: `data-action="delete"` + `data-id="123"`
- Tabs: `data-action="switch-tab"` + `data-tab="tab-name"`

**Icons:** All buttons should include a Font Awesome icon before the text:
```html
<button class="btn-primary" data-action="add" data-page="items">
    <i class="fas fa-plus"></i> Add Item
</button>
```

### Dropdowns
```css
height: 45px;
padding: 0 16px;
background: var(--bg);
border: 1px solid var(--border);
border-radius: 8px;
color: var(--text-primary);
font-size: 14px;
font-family: 'Inter', sans-serif;
```

**Dropdown Options:**
- Background: `#16161F` (card background)
- Hover: `#6B46C1` (primary color, NO checkered pattern)
- Selected: Linear gradient `#6B46C1` to `#7C3AED`
- Font: 'Inter', sans-serif, 14px
- Border radius: 4px
- Padding: 12px 16px

**Important:** Options must use consistent Inter font and clean hover states without browser default patterns.

### Scrollbars
```css
::-webkit-scrollbar { width: 8px; height: 8px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: var(--border-light); }
```

---

## Implementation Notes

1. Use CSS variables for all colors
2. Maintain consistent spacing (12px, 16px, 20px, 24px)
3. Ensure proper contrast ratios for accessibility
4. Test keyboard navigation on all interactive elements
5. Validate responsive behavior on mobile, tablet, and desktop
6. All buttons MUST have appropriate `data-action` attributes
7. Icons should use Font Awesome and be placed before button text
8. Forms should either use traditional `action="process_*.php"` OR `data-ajax="true"` for AJAX
9. Tabs can use query parameter navigation OR JavaScript switching with `data-action="switch-tab"`
10. Dropdown options must maintain theme styling and Inter font consistency

---

## Common Issues and Solutions

### Issue: Button reloads to home page
**Cause:** Missing `data-page`, `data-modal`, or proper action handler  
**Solution:** Add `data-page="destination-page"` to navigation buttons

### Issue: Tabs don't switch
**Cause:** Missing `data-action="switch-tab"` and `data-tab` attributes  
**Solution:** Add both attributes: `<button data-action="switch-tab" data-tab="tab-name">`

### Issue: Dropdown shows checkered background
**Cause:** Browser default styling  
**Solution:** Use custom CSS in `shared_styles.css` with proper option styling

### Issue: Form submission redirects unexpectedly
**Cause:** Process file using `header()` redirect instead of JSON response  
**Solution:** For AJAX forms, return JSON. For traditional forms, redirect is expected.

---

## Version History

- **v1.1** - January 22, 2026 - Added button data attributes guide, dropdown improvements, common issues section

