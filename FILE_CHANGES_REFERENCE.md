# File Changes Quick Reference

## CRITICAL FIXES (Priority 1)

### 1. `my-courses.php`
**Issue**: Links to non-existent `course.php` instead of `courses.php`

**Changes**:
- Line 143: `course.php` → `courses.php`
- Line 160: `course.php` → `courses.php`  
- Line 218: `course.php` → `courses.php`

### 2. `mobile-payment-process.php`
**Issue**: Payment crash due to session loss

**Changes**:
- Add localStorage backup before redirect (around line 296)
- Add WebView detection
- Improve error handling in payment handler

### 3. `mobile-verify-payment.php`
**Issue**: Session recovery needed

**Changes**:
- Add session recovery from localStorage (around line 7)
- Add payment_logs lookup as fallback
- Better error messages

### 4. `includes/nav.php`
**Issue**: Profile icon navigation

**Changes**:
- Line 32: Add click handler → `profile.php`
- Ensure profile icon is clickable

---

## FEATURE ADDITIONS (Priority 2)

### 5. `auth/signup.php`
**Change**: Make phone mandatory

**Changes**:
- Line 145: Add `required` attribute to phone input
- Line 43: Add phone validation check
- Add phone format validation (10-15 digits)

### 6. `includes/menu.php`
**Change**: Add Privacy/Terms dropdown

**Changes**:
- Add menu items:
  - `<a class="nav-link" href="privacy.php">Privacy Policy</a>`
  - `<a class="nav-link" href="terms.php">Terms & Conditions</a>`

### 7. `includes/footer.php`
**Changes**:
- Add WhatsApp button (floating or 5th icon)
- Add Test Series icon (5th icon in footer)
- Link: `test-series.php` or `course-quiz.php`

### 8. `course-details.php`
**Changes**:
- Line 1183: Change `autoPlay = false` → `autoPlay = true`
- Add redirect after free modules complete
- Verify reviews section displays correctly

### 9. `home.php`
**Changes**:
- Lines 220-223: Add course_id to slider links
- Modify slider to link: `course-details.php?id={course_id}`

---

## NEW FILES TO CREATE

### 10. `config/razorpay-webhook.php` (NEW)
**Purpose**: Server-side webhook handler

### 11. `config/payment-recovery.php` (NEW)
**Purpose**: Recover failed enrollments

### 12. `config/points-system.php` (NEW)
**Purpose**: Points management functions

### 13. `privacy.php` (NEW)
**Purpose**: Privacy Policy page

### 14. `terms.php` (NEW)
**Purpose**: Terms & Conditions page

### 15. `certificates.php` (NEW)
**Purpose**: Certificates repository

### 16. `assets/js/mobile.js` (NEW or UPDATE)
**Purpose**: WebView utilities

---

## DATABASE CHANGES

### Execute SQL File
**File**: `database/new_tables.sql`

**Tables to Create**:
1. `user_certificates`
2. `user_points`
3. `points_transactions`
4. `ecommerce_products` (optional)
5. `ecommerce_orders` (optional)
6. `ecommerce_order_items` (optional)

---

## TESTING CHECKLIST

- [ ] Payment flow works without crash
- [ ] Profile icon navigates correctly
- [ ] Phone mandatory in signup
- [ ] All "Browse Courses" links work
- [ ] Auto-play works in courses
- [ ] WhatsApp button appears
- [ ] Test Series icon in footer
- [ ] Privacy/Terms in menu
- [ ] Course banners link correctly
- [ ] Certificates accessible after completion
- [ ] Points system functional

---

## IMPLEMENTATION ORDER

1. **First**: Fix payment crash (files 2, 3)
2. **Second**: Fix routing (file 1, 4)
3. **Third**: Add features (files 5-9)
4. **Fourth**: Create new files (files 10-16)
5. **Fifth**: Execute database changes

---

**Last Updated**: 2024
