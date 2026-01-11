#!/usr/bin/env python3
"""
Single build script for WordPress plugin zip file.
Creates zip with correct structure for WordPress updates.
"""
import os
import shutil
import tempfile
import zipfile
from datetime import datetime

# Configuration
PLUGIN_FOLDER = 'checkout-com-unified-payments-api'
MAIN_FILE = 'woocommerce-gateway-checkout-com.php'
PLUGIN_NAME = 'Checkout.com Payment Gateway'

# Get script directory
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
os.chdir(SCRIPT_DIR)

print('=' * 70)
print('📦 Building WordPress Plugin Zip File')
print('=' * 70)
print(f'Plugin Folder: {PLUGIN_FOLDER}')
print(f'Main File: {MAIN_FILE}')
print(f'Plugin Name: {PLUGIN_NAME}')
print('')

# Verify main file exists (check in plugin folder)
main_file_path = os.path.join(PLUGIN_FOLDER, MAIN_FILE)
if not os.path.exists(main_file_path):
    print(f'❌ ERROR: Main plugin file not found: {main_file_path}')
    exit(1)

# Verify Plugin Name in header
with open(main_file_path, 'r', encoding='utf-8') as f:
    content = f.read()
    if f'Plugin Name: {PLUGIN_NAME}' not in content:
        print('⚠️  WARNING: Plugin Name header might not match!')
    else:
        print('✅ Plugin Name header verified')

# Create timestamp
timestamp = datetime.now().strftime('%Y%m%d-%H%M%S')
zip_name = f'{PLUGIN_FOLDER}-{timestamp}.zip'

print('')
print(f'📦 Building zip file: {zip_name}')
print('')

# Create temp directory with plugin folder structure
temp_dir = tempfile.mkdtemp()
plugin_dir = os.path.join(temp_dir, PLUGIN_FOLDER)
os.makedirs(plugin_dir, exist_ok=True)

print('📁 Creating plugin folder structure...')

# Files/directories to exclude
exclude_patterns = [
    '.git', '.gitignore', '.zip', '.md', 'tests', '.log',
    'node_modules', '.DS_Store', '__MACOSX', 'backups',
    '*-backup-*', 'check-domain-association-file.php',
    'diagnose-*.php', 'generate-*.php', 'test-*.php',
    'terms-and-conditions-checkbox.php', 'create-zip.py',
    'build-zip.sh', 'build-webhook-queue-zip.sh',
    'build-plugin-zip.py', 'build-correct-zip.sh',
    'check-zip-structure.py', 'verify-and-fix-zip.py',
    'diagnose-header-error.py', 'build.py', 'php-uploads.ini'
]

def should_exclude(path):
    """Check if path should be excluded"""
    path_lower = path.lower()
    for pattern in exclude_patterns:
        if pattern.lower() in path_lower:
            return True
    return False

# Copy files from plugin folder only
copied_count = 0
plugin_source_dir = os.path.join(SCRIPT_DIR, PLUGIN_FOLDER)

if not os.path.exists(plugin_source_dir):
    print(f'❌ ERROR: Plugin folder not found: {plugin_source_dir}')
    shutil.rmtree(temp_dir)
    exit(1)

for root, dirs, files in os.walk(plugin_source_dir):
    # Filter excluded directories
    dirs[:] = [d for d in dirs if not should_exclude(d)]
    
    for file in files:
        if should_exclude(file):
            continue
        
        src = os.path.join(root, file)
        if should_exclude(src):
            continue
        
        # Calculate relative path from plugin folder
        rel_path = os.path.relpath(src, plugin_source_dir)
        dst = os.path.join(plugin_dir, rel_path)
        
        try:
            os.makedirs(os.path.dirname(dst), exist_ok=True)
            shutil.copy2(src, dst)
            copied_count += 1
        except Exception as e:
            print(f'⚠️  Warning: Could not copy {src}: {e}')

print(f'✅ Copied {copied_count} files')

# Verify main file exists in plugin directory
main_file_path = os.path.join(plugin_dir, MAIN_FILE)
if not os.path.exists(main_file_path):
    print(f'❌ ERROR: Main plugin file not found in plugin directory!')
    print(f'   Expected: {main_file_path}')
    shutil.rmtree(temp_dir)
    exit(1)

# Check if vendor directory exists (required for SDK)
vendor_autoload = os.path.join(plugin_dir, 'vendor', 'autoload.php')
if not os.path.exists(vendor_autoload):
    print('⚠️  WARNING: vendor/autoload.php not found!')
    print('   The plugin requires vendor dependencies. Please ensure vendor/ folder exists.')
    print('   You may need to run \'composer install\' or copy vendor from Release folder.')
else:
    print('✅ Vendor dependencies found')

# Create zip from temp directory
print('📦 Creating zip archive...')
zip_path = os.path.join(SCRIPT_DIR, zip_name)

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(plugin_dir):
        for file in files:
            file_path = os.path.join(root, file)
            arcname = os.path.relpath(file_path, temp_dir)
            zipf.write(file_path, arcname)

# Cleanup
shutil.rmtree(temp_dir)

# Verify zip structure
print('')
print('🔍 Verifying zip structure...')
expected_path = f'{PLUGIN_FOLDER}/{MAIN_FILE}'

with zipfile.ZipFile(zip_path, 'r') as verify_zip:
    files = verify_zip.namelist()
    if expected_path in files:
        print(f'   ✅ Correct structure verified: {expected_path}')
    else:
        print(f'   ❌ ERROR: Zip structure is incorrect!')
        print(f'   Expected: {expected_path}')
        print('   First 5 files in zip:')
        for f in files[:5]:
            print(f'     - {f}')
        exit(1)

file_count = len(files)
zip_size = os.path.getsize(zip_path) / 1024 / 1024

print('')
print('=' * 70)
print('✅ SUCCESS: Plugin zip created with correct structure!')
print('=' * 70)
print(f'📁 File: {zip_name}')
print(f'💾 Size: {zip_size:.2f} MB')
print(f'📊 Files: {file_count}')
print('')
print('🔑 WordPress Update Identifiers:')
print(f'   1. ✅ Folder name: {PLUGIN_FOLDER}')
print(f'   2. ✅ Main file: {MAIN_FILE}')
print(f'   3. ✅ Plugin Name: {PLUGIN_NAME}')
print('')
print('💡 This zip will UPDATE existing plugin installations')
print('   Merchants will NOT see duplicate plugins!')
print('=' * 70)




