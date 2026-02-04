#!/usr/bin/env python3
"""
Script to remove all clinic_id parameters from repository files
"""
import os
import re
import glob

def remove_clinic_id_from_file(filepath):
    """Remove all clinic_id parameters and references from a PHP file"""
    with open(filepath, 'r') as f:
        content = f.read()
    
    original = content
    
    # Remove union type parameters like: ?string|int $clinicId = null (first param)
    content = re.sub(r'\(\s*\?string\|int\s+\$clinicId\s*=\s*null\s*\)', '()', content)
    
    # Remove union type parameters like: ?string|int $clinicId, (first param with other params after)
    content = re.sub(r'\(\s*\?string\|int\s+\$clinicId\s*,', '(', content)
    
    # Remove union type parameters like: , ?string|int $clinicId = null
    content = re.sub(r',\s*\?string\|int\s+\$clinicId\s*=\s*null', '', content)
    
    # Remove union type parameters like: string|int $clinicId (non-nullable, first param)
    content = re.sub(r'\(\s*string\|int\s+\$clinicId\s*,', '(', content)
    
    # Remove union type parameters like: string|int $clinicId) (non-nullable, only param)
    content = re.sub(r'\(\s*string\|int\s+\$clinicId\s*\)', '()', content)
    
    # Remove union type parameters like: , string|int $clinicId
    content = re.sub(r',\s*string\|int\s+\$clinicId', '', content)
    
    # Remove standalone parameters like: , ?int $clinicId = null
    content = re.sub(r',\s*\?\w+\s+\$clinicId\s*=\s*null', '', content)
    
    # Remove standalone parameters like: , int $clinicId
    content = re.sub(r',\s*\w+\s+\$clinicId\s*(?=[,\)])', '', content)
    
    # Remove standalone parameters like: (?int $clinicId = null) (first param)
    content = re.sub(r'\(\s*\?\w+\s+\$clinicId\s*=\s*null\s*\)', '()', content)
    
    # Remove standalone parameters like: (int $clinicId, ...) (first param)
    content = re.sub(r'\(\s*\w+\s+\$clinicId\s*,', '(', content)
    
    # Remove the conditional blocks: if ($clinicId !== null) { $query->where('clinic_id', $clinicId); }
    # Handle both single-line and multi-line versions
    content = re.sub(
        r'\s*//\s*Filter by clinic if provided\s*\n\s*if\s*\(\s*\$clinicId\s*!==\s*null\s*\)\s*\{\s*\n\s*\$query->where\([\'"](?:clinic|clinics)_id[\'"]\s*,\s*\$clinicId\);\s*\n\s*\}',
        '',
        content
    )
    
    # Remove conditional blocks without comment
    content = re.sub(
        r'\n\s*if\s*\(\s*\$clinicId\s*!==\s*null\s*\)\s*\{\s*\n\s*\$query->where\([\'"](?:clinic|clinics)_id[\'"]\s*,\s*\$clinicId\);\s*\n\s*\}',
        '',
        content
    )
    
    # Remove where clauses with $clinicId
    content = re.sub(
        r'\n\s*->where\([\'"](?:clinic|clinics)_id[\'"]\s*,\s*\$clinicId\)',
        '',
        content
    )
    
    # Remove method calls passing $clinicId
    content = re.sub(
        r',\s*\$clinicId\)',
        ')',
        content
    )
    
    # Remove methods that only deal with clinicId (like getByClinic)
    content = re.sub(
        r'\/\*\*[\s\*]*Get \w+ by clinic[\s\*]*\*\/\s*public function getByClinic\([^)]*\)[^{]*\{[^}]*\}',
        '',
        content,
        flags=re.DOTALL
    )
    
    # Remove trailing commas in parameters: , )
    content = re.sub(r',\s*\)', ')', content)
    
    # Remove empty comment lines left behind
    content = re.sub(r'\n\s*//\s*Filter by clinic if provided\s*\n\s*\n', '\n\n', content)
    
    # Write back only if changed
    if content != original:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"✓ Updated: {filepath}")
        return True
    else:
        print(f"- No changes: {filepath}")
        return False

def main():
    """Main function to process all repository files"""
    repo_path = 'app/Repositories'
    php_files = glob.glob(f'{repo_path}/**/*.php', recursive=True)
    
    print(f"Found {len(php_files)} PHP files in {repo_path}")
    print("=" * 60)
    
    updated_count = 0
    for filepath in php_files:
        if remove_clinic_id_from_file(filepath):
            updated_count += 1
    
    print("=" * 60)
    print(f"✓ Updated {updated_count} files")
    print(f"- Skipped {len(php_files) - updated_count} files (no changes needed)")

if __name__ == '__main__':
    main()
