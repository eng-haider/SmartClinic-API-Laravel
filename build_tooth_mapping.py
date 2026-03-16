import re
from collections import defaultdict

# Extract all (tooth_id, part_id, d_attribute) from old file
with open('/Users/haideraltemimy/Documents/GitHub/clinic_management_systems/src/components/core/teeth_v2.vue', 'r') as f:
    old_content = f.read()

# Extract all (tooth_id, part_id, d_attribute) from new file
with open('/Users/haideraltemimy/Documents/GitHub/SmartClinic-Front/src/components/teeth/teethData.js', 'r') as f:
    new_content = f.read()

# Parse old file
old_entries = []
old_tooth_blocks = re.split(r'\{\s*\n\s*id:\s*"tooth-', old_content)
for block in old_tooth_blocks[1:]:
    tid_match = re.match(r'(\d+)"', block)
    if not tid_match:
        continue
    tid = int(tid_match.group(1))
    tnum_match = re.search(r'tooth_num:\s*(\d+|"?\d+"?)', block)
    tnum = int(tnum_match.group(1).strip('"')) if tnum_match else None
    part_pattern = r'id:\s*(\d+),\s*\n?\s*svg:\s*`<path[^>]*d="([^"]+)"'
    parts = re.findall(part_pattern, block)
    for pid, d_attr in parts:
        d_norm = ' '.join(d_attr.split())
        old_entries.append({
            'tooth_id': f'tooth-{tid}',
            'tooth_num': tnum,
            'part_id': int(pid),
            'd': d_norm
        })

# Parse new file
new_entries = []
new_tooth_blocks = re.split(r'\{\s*\n?\s*id:\s*"tooth-', new_content)
for block in new_tooth_blocks[1:]:
    tid_match = re.match(r'(\d+)"', block)
    if not tid_match:
        continue
    tid = int(tid_match.group(1))
    tnum_match = re.search(r'tooth_num:\s*(\d+)', block)
    tnum = int(tnum_match.group(1)) if tnum_match else None
    part_pattern = r'\{\s*id:\s*(\d+),\s*svg:\s*`<path[^>]*d="([^"]+)"'
    parts = re.findall(part_pattern, block)
    for pid, d_attr in parts:
        d_norm = ' '.join(d_attr.split())
        new_entries.append({
            'tooth_id': f'tooth-{tid}',
            'tooth_num': tnum,
            'part_id': int(pid),
            'd': d_norm
        })

print(f"Old entries: {len(old_entries)}")
print(f"New entries: {len(new_entries)}")
print()

# Build mapping
mapping = {}
matched = 0
unmatched_list = []
for old in old_entries:
    found = False
    for new in new_entries:
        if old['d'] == new['d']:
            key = f"{old['tooth_id']}_{old['part_id']}"
            mapping[key] = {
                'old_tooth_id': old['tooth_id'],
                'old_part_id': old['part_id'],
                'old_tooth_num': old['tooth_num'],
                'new_tooth_id': new['tooth_id'],
                'new_part_id': new['part_id'],
                'new_tooth_num': new['tooth_num']
            }
            matched += 1
            found = True
            break
    if not found:
        unmatched_list.append(old)

print(f"Matched: {matched}, Unmatched: {len(unmatched_list)}")
print()

# Print unmatched entries
if unmatched_list:
    print("=== UNMATCHED OLD ENTRIES ===")
    for u in unmatched_list[:20]:
        print(f"  {u['tooth_id']} (tooth_num={u['tooth_num']}, part={u['part_id']})")
        print(f"    d={u['d'][:80]}...")
    print()

# Group by tooth_num and print mapping
by_tooth_num = defaultdict(list)
for key, val in sorted(mapping.items()):
    tnum = val['old_tooth_num'] or val['new_tooth_num']
    by_tooth_num[tnum].append(val)

for tnum in sorted(by_tooth_num.keys()):
    entries = by_tooth_num[tnum]
    print(f"--- Tooth {tnum} ---")
    for e in entries:
        print(f"  {e['old_tooth_id']} (part {e['old_part_id']}) -> {e['new_tooth_id']} (part {e['new_part_id']})")

# Generate JS mapping object
print("\n\n=== JAVASCRIPT MAPPING ===\n")
print("export const OLD_TO_NEW_TOOTH_MAP = {")
for key, val in sorted(mapping.items()):
    old_tid = val['old_tooth_id']
    old_pid = val['old_part_id']
    new_tid = val['new_tooth_id']
    new_pid = val['new_part_id']
    print(f'  "{old_tid}_{old_pid}": {{ toothId: "{new_tid}", partId: {new_pid} }},')
print("};")
