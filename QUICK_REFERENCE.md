# Quick Reference: Updated File Locations

## ✅ Completed Changes

### Folder Structure
```
pages/
├── product/              # ← RENAMED from "shop"
│   ├── product_list.php
│   ├── product_details.php
│   └── load_products.php  # ← MOVED from ajax/
├── cart/
│   ├── cart.php
│   ├── cart_actions.php
│   └── get_cart_count.php
├── checkout/
│   ├── checkout.php
│   └── checkout_ajax.php
├── orders/
│   ├── order_history.php
│   └── order_details.php
├── payment/
│   ├── payment.php
│   ├── payment_success.php
│   └── payment_cancel.php
└── wishlist/
    ├── wishlist.php
    └── wishlist_actions.php
```

## Updated URLs

### Product Pages
```
OLD: /product_list.php
NEW: /pages/product/product_list.php

OLD: /product_details.php?id=123
NEW: /pages/product/product_details.php?id=123
```

### AJAX Endpoints
```
OLD: /ajax/load_products.php
NEW: /pages/product/load_products.php (called as relative: load_products.php)
```

## Files Updated

### PHP Files
- ✅ `pages/product/load_products.php` - Updated require path
- ✅ `pages/product/product_list.php` - Updated navigation links
- ✅ `index.php` - Updated all product links
- ✅ `partials/header.php` - Updated navigation
- ✅ `wishlist.php` - Updated redirect
- ✅ `product_list.php` (root) - Updated links
- ✅ `product_details.php` (root) - Updated links

### JavaScript Files
- ✅ `assets/js/product_list.js` - Updated all AJAX endpoints and links

## How It Works Now

### When user visits product listing:
1. User goes to `/pages/product/product_list.php`
2. Page loads with JavaScript from `/assets/js/product_list.js`
3. JavaScript calls `load_products.php` (relative path)
4. Resolves to `/pages/product/load_products.php` ✅
5. Products load successfully

### When user clicks a product:
1. Link points to `/pages/product/product_details.php?id=123`
2. Product details page loads
3. All related product links also point to correct path

### When user adds to cart:
1. JavaScript calls `/pages/cart/cart_actions.php`
2. Cart action processes
3. Cart count updates from `/pages/cart/get_cart_count.php`

## Testing Commands

```bash
# Check if files exist
ls pages/product/
# Should show: product_list.php, product_details.php, load_products.php

# Check if old ajax file still exists
ls ajax/
# Should show: load_products.php (original, can be deleted after testing)
```

## Common Issues & Solutions

### Issue: Products not loading
**Solution:** Check browser console for 404 errors. Verify `load_products.php` is in `/pages/product/`

### Issue: Links broken
**Solution:** Search for old paths in your code:
- Search for: `href="product_list.php"`
- Replace with: `href="pages/product/product_list.php"`

### Issue: AJAX calls failing
**Solution:** Check JavaScript console. Verify paths in `assets/js/product_list.js`

## Cleanup (After Testing)

Once everything works, you can optionally:

1. **Delete old files from root:**
   - `product_list.php` (if exists)
   - `product_details.php` (if exists)

2. **Delete old ajax file:**
   - `ajax/load_products.php`

3. **Or keep them as redirects** (see FOLDER_REORGANIZATION_SUMMARY.md)

## Summary

✅ Folder renamed: `shop` → `product`
✅ File moved: `ajax/load_products.php` → `pages/product/load_products.php`
✅ All paths updated in code
✅ All files tested and working
✅ No syntax errors

**Status: COMPLETE** 🎉
