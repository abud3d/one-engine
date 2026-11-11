"""Dependency and packaging constraints, without third-party tooling."""
from pathlib import Path
import re

root = Path(__file__).resolve().parent.parent
rules = {
    'src/Core': ('OneEngine\\Modules', 'OneEngine\\Storage', 'OneEngine\\Security'),
    'src/Storage': ('OneEngine\\Modules',),
    'src/Modules/Runtime': ('OneEngine\\Modules\\Builder', 'OneEngine\\Modules\\CentralManagement'),
}
for directory, forbidden in rules.items():
    for path in (root / directory).rglob('*.php'):
        text = path.read_text()
        for dependency in forbidden:
            assert dependency not in text, (path, dependency)

for path in (root / 'public').rglob('*'):
    if path.is_file():
        assert path.suffix in {'.php', '.css'} or path.relative_to(root / 'public').as_posix() == 'assets/preview-shell.js', path
        assert not path.is_symlink(), path

for name in ['ARCHITECTURE', 'PERMISSIONS', 'STORAGE', 'MIGRATION', 'SOURCE_AUDIT', 'OPERATIONS']:
    assert (root / f'docs/{name}.md').stat().st_size > 100, name
assert not (root / 'composer.json').exists()
assert not (root / 'package.json').exists()
assert 'abud3d/arbnt' in (root / 'NOTICE.md').read_text()
for path in (root / 'src').rglob('*.php'):
    assert not re.search(r'\b(curl_exec|stream_socket_client|fsockopen)\s*\(', path.read_text()), path
print('Module boundaries, public root and documentation checks passed.')
