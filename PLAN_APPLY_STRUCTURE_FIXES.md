# Plan: Apply Folder Structure & Multiple Installation Fixes

## Current State Analysis

### ✅ What's Already Done
- Branch: `refactor/reduce-complexity`
- Some directories exist at root: `includes/`, `flow-integration/`
- Build script exists: `bin/build.sh`

### ❌ What Needs to Be Fixed

1. **Folder Structure Issue:**
   - `checkout-com-unified-payments-api/` directory still exists (old structure)
   - Main plugin file: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
   - Plugin files nested in subdirectory instead of root

2. **Build Script Issue:**
   - `PLUGIN_SOURCE_DIR="${PLUGIN_FOLDER}"` (points to `checkout-com-unified-payments-api/`)
   - Should be `PLUGIN_SOURCE_DIR="."` (root directory)

3. **Missing from Branch:**
   - Commit `aae35f6` (the fix) exists in `upstream/master` but not in this branch
   - Branch diverged before the fix was merged

---

## Implementation Plan

### Phase 1: Pre-Migration Analysis & Backup

#### Step 1.1: Create Backup
- [ ] Create a backup branch: `backup/before-structure-fix`
- [ ] Document current file structure
- [ ] List all files in `checkout-com-unified-payments-api/`

#### Step 1.2: Identify Files to Move
- [ ] List all files/directories in `checkout-com-unified-payments-api/`
- [ ] Check for conflicts (files that exist both in root and subdirectory)
- [ ] Document any custom changes in the branch that might conflict

#### Step 1.3: Verify Git Status
- [ ] Ensure working tree is clean
- [ ] Check for uncommitted changes
- [ ] Verify branch is up to date with remote

---

### Phase 2: File Structure Migration

#### Step 2.1: Move Plugin Files to Root
Move all files from `checkout-com-unified-payments-api/` to repository root:

**Directories to move:**
- [ ] `checkout-com-unified-payments-api/assets/` → `assets/`
- [ ] `checkout-com-unified-payments-api/includes/` → `includes/` (check for conflicts)
- [ ] `checkout-com-unified-payments-api/flow-integration/` → `flow-integration/` (check for conflicts)
- [ ] `checkout-com-unified-payments-api/lib/` → `lib/`
- [ ] `checkout-com-unified-payments-api/vendor/` → `vendor/`
- [ ] `checkout-com-unified-payments-api/languages/` → `languages/`
- [ ] `checkout-com-unified-payments-api/templates/` → `templates/`

**Files to move:**
- [ ] `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php` → `woocommerce-gateway-checkout-com.php`
- [ ] `checkout-com-unified-payments-api/readme.txt` → `readme.txt`
- [ ] `checkout-com-unified-payments-api/check-database-indexes.php` → `check-database-indexes.php`
- [ ] `checkout-com-unified-payments-api/view-webhook-queue.php` → `view-webhook-queue.php`

#### Step 2.2: Handle Conflicts
- [ ] If `includes/` exists in both locations:
  - Compare contents
  - Merge if needed (prefer root version if identical)
  - Document any differences
- [ ] If `flow-integration/` exists in both locations:
  - Compare contents
  - Merge if needed
  - Document any differences

#### Step 2.3: Remove Empty Directory
- [ ] Verify all files are moved
- [ ] Remove `checkout-com-unified-payments-api/` directory
- [ ] Verify no important files remain

---

### Phase 3: Build Script Update

#### Step 3.1: Update Build Script
Modify `bin/build.sh`:

**Change:**
```bash
PLUGIN_SOURCE_DIR="${PLUGIN_FOLDER}"  # OLD
```

**To:**
```bash
PLUGIN_SOURCE_DIR="."  # NEW - root directory
```

#### Step 3.2: Verify Build Script Logic
- [ ] Ensure copy commands work with root directory
- [ ] Verify zip structure will be correct: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
- [ ] Check all file paths in script are correct

---

### Phase 4: Verification & Testing

#### Step 4.1: Structure Verification
- [ ] Verify main plugin file exists at root: `woocommerce-gateway-checkout-com.php`
- [ ] Verify all directories exist at root level
- [ ] Check that `checkout-com-unified-payments-api/` directory is removed
- [ ] Verify no duplicate files exist

#### Step 4.2: Build Script Test
- [ ] Run build script: `./bin/build.sh`
- [ ] Verify zip file is created successfully
- [ ] Check zip structure: `unzip -l checkout-com-unified-payments-api.zip | head -20`
- [ ] Verify main file path: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
- [ ] Check zip size and file count are reasonable

#### Step 4.3: Git Status Check
- [ ] Run `git status` to see all changes
- [ ] Verify file renames are detected correctly (should show as renames, not deletions+additions)
- [ ] Check for any unexpected files

---

### Phase 5: Code Path Verification

#### Step 5.1: Check Internal Path References
Verify that internal path references still work:

- [ ] Check `__DIR__` usage in main plugin file
- [ ] Check `dirname(__DIR__)` usage in includes
- [ ] Verify autoloader paths (`vendor/autoload.php`)
- [ ] Check asset paths (CSS, JS, images)
- [ ] Verify template paths

#### Step 5.2: Test Critical Functions
- [ ] Plugin activation/deactivation
- [ ] Settings page loading
- [ ] Asset loading (CSS, JS)
- [ ] Template rendering

---

### Phase 6: Git Operations

#### Step 6.1: Stage Changes
- [ ] Stage all file moves: `git add -A`
- [ ] Review changes: `git status`
- [ ] Verify renames are detected (should show `R100` not `D` + `A`)

#### Step 6.2: Commit Changes
- [ ] Create commit with descriptive message:
  ```
  fix: Restructure plugin files to WordPress standards and fix multiple installation issue
  
  - Move all plugin files from checkout-com-unified-payments-api/ to repository root
  - Update build.sh to use root directory as source
  - Fixes multiple installation issue by ensuring correct zip structure
  - Aligns with WordPress plugin development guidelines
  
  Related: #401
  ```

#### Step 6.3: Verify Commit
- [ ] Review commit: `git show HEAD --stat`
- [ ] Verify file count matches expected
- [ ] Check that renames are preserved in git history

---

## Risk Mitigation

### Potential Issues & Solutions

1. **Conflict: Files exist in both locations**
   - **Solution:** Compare files, prefer root version if identical, merge if different

2. **Build script fails**
   - **Solution:** Test incrementally, verify paths before running full build

3. **Git doesn't detect renames**
   - **Solution:** Use `git add -A` to ensure renames are detected, or manually stage renames

4. **Path references break**
   - **Solution:** Verify all `__DIR__` and path references work correctly

5. **Missing files**
   - **Solution:** Compare file lists before and after migration

---

## Success Criteria

✅ All plugin files moved to repository root  
✅ `checkout-com-unified-payments-api/` directory removed  
✅ Build script updated to use root directory  
✅ Build script creates correct zip structure  
✅ Zip file contains: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`  
✅ All internal path references work correctly  
✅ Git history preserves file renames  
✅ No duplicate files or directories  

---

## Rollback Plan

If something goes wrong:

1. **Restore from backup branch:**
   ```bash
   git checkout backup/before-structure-fix
   ```

2. **Or reset to previous commit:**
   ```bash
   git reset --hard HEAD~1
   ```

3. **Or restore specific files:**
   ```bash
   git checkout HEAD~1 -- path/to/file
   ```

---

## Estimated Time

- **Phase 1 (Analysis):** 15 minutes
- **Phase 2 (Migration):** 30-45 minutes
- **Phase 3 (Build Script):** 10 minutes
- **Phase 4 (Verification):** 20-30 minutes
- **Phase 5 (Path Verification):** 15-20 minutes
- **Phase 6 (Git Operations):** 10 minutes

**Total:** ~2 hours (including testing and verification)

---

## Notes

- This fix is based on commit `aae35f6` from `upstream/master`
- The branch has diverged, so we're applying the fix manually rather than merging
- Some directories may already exist at root (like `includes/`, `flow-integration/`) - need to handle carefully
- The build script structure is different from master, so we need to adapt the fix
