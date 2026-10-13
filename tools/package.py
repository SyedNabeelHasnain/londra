import argparse
import base64
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import sys
import tempfile
import zipfile


ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / "londra-private"
PUBLIC = ROOT / "public_html"
PRIVATE_DIRECTORIES = {"app", "bootstrap", "config", "database", "lang", "resources", "routes", "vendor", "storage"}
PRIVATE_FILES = {".htaccess", "artisan", "composer.json", "composer.lock", "package.json", "package-lock.json", "vite.config.js", "LICENSES"}
WRITABLE_DIRECTORIES = (
    "bootstrap/cache",
    "storage/app/private",
    "storage/app/public/media",
    "storage/framework/cache/data",
    "storage/framework/sessions",
    "storage/framework/views",
    "storage/logs",
)
BUILD_ENTRIES = {"resources/css/app.css", "resources/css/admin.css", "resources/js/app.js"}
PUBLIC_REQUIRED = (
    ".htaccess",
    "index.php",
    "assets/theme.js",
    "assets/recovery.css",
    "assets/admin-system-fonts.css",
    "css/filament/filament/app.css",
    "js/filament/filament/app.js",
    "js/filament/forms/components/file-upload.js",
    "fonts/filament/filament/inter/index.css",
    "fonts/filament/filament/inter/LICENSE.txt",
)


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def relative_name(path):
    return path.relative_to(ROOT).as_posix()


def prepare():
    require(PRIVATE.is_dir() and PUBLIC.is_dir(), "Both application folders must exist.")
    for relative in WRITABLE_DIRECTORIES:
        path = PRIVATE / relative
        require(not path.is_symlink(), "Writable directories must not be symbolic links.")
        path.mkdir(parents=True, exist_ok=True)
    for path in (PRIVATE / "bootstrap/cache").glob("*.php"):
        require(path.is_file() and not path.is_symlink(), "Unexpected bootstrap cache entry.")
        path.unlink()


def selected_private(path):
    relative = path.relative_to(PRIVATE)
    if len(relative.parts) == 1:
        return relative.name in PRIVATE_FILES
    first = relative.parts[0]
    if first not in PRIVATE_DIRECTORIES:
        return False
    if first == "bootstrap" and relative.parts[1] == "cache":
        return False
    if first == "storage":
        return len(relative.parts) > 2 and relative.parts[1] == "app" and relative.parts[2] in {"brand-sources", "geoip"}
    return True


def selected_public(path):
    relative = path.relative_to(PUBLIC)
    return relative.parts[0] not in {"hot", "media", "londra-install.php"} and not relative.name.startswith(".londra-")


def files_under(directory, selected):
    result = []
    for current, directories, files in os.walk(directory, followlinks=False):
        current_path = Path(current)
        directories.sort()
        files.sort()
        retained = []
        for name in directories:
            path = current_path / name
            if path.relative_to(directory).parts[0] in {"node_modules", "tests", "reports", ".phpunit.cache"}:
                continue
            require(not path.is_symlink(), "Application folders must not contain symbolic links.")
            retained.append(name)
        directories[:] = retained
        for name in files:
            path = current_path / name
            if not selected(path):
                continue
            require(path.is_file() and not path.is_symlink(), "Application files must be regular files.")
            if directory == PRIVATE and path.relative_to(PRIVATE).parts[0] != "vendor":
                require(name not in {".env", ".londra-setup-key", ".londra-installed"} and not name.startswith(".env."), "Unexpected private configuration file.")
                require(path.suffix.lower() not in {".log", ".sql", ".sqlite", ".sqlite3", ".bak", ".tmp", ".zip"}, "Unexpected runtime or temporary file.")
            if directory == PUBLIC:
                require(not name.startswith(".env") and path.suffix.lower() not in {".log", ".sql", ".sqlite", ".sqlite3", ".bak", ".tmp", ".zip", ".map"}, "Unexpected public runtime or temporary file.")
            result.append(path)
    return result


def verify_dependencies():
    for name in ("vendor/autoload.php", "vendor/composer/installed.json", "vendor/composer/installed.php", "vendor/composer/platform_check.php", "bootstrap/app.php", "bootstrap/providers.php", "LICENSES"):
        require((PRIVATE / name).is_file(), "Required application dependency is missing: " + name)
    lock = json.loads((PRIVATE / "composer.lock").read_text())
    installed = json.loads((PRIVATE / "vendor/composer/installed.json").read_text())
    require(isinstance(installed, dict) and installed.get("dev") is False and installed.get("dev-package-names") == [], "Install production dependencies with composer install --no-dev before packaging.")
    expected = {(package["name"], package["version"]) for package in lock["packages"]}
    actual = {(package["name"], package["version"]) for package in installed["packages"]}
    require(expected == actual, "Installed production dependencies do not match composer.lock.")
    production_names = {package["name"] for package in lock["packages"]}
    for package in lock["packages-dev"]:
        if package["name"] not in production_names:
            require(not (PRIVATE / "vendor" / package["name"]).exists(), "An uninstalled development dependency remains in vendor.")
    expected_bins = {PurePosixPath(binary).name for package in installed["packages"] for binary in package.get("bin", [])}
    actual_bins = {path.name for path in (PRIVATE / "vendor/bin").iterdir()}
    require(expected_bins == actual_bins, "The vendor binary directory contains missing or obsolete dependency commands.")
    require(not (PRIVATE / "vendor/bin/phpunit").exists(), "Development dependencies must not be packaged.")
    return len(expected)


def verify_build():
    build = PUBLIC / "build"
    manifest_path = build / "manifest.json"
    require(manifest_path.is_file(), "Compile production assets before packaging.")
    manifest = json.loads(manifest_path.read_text())
    require(BUILD_ENTRIES.issubset(manifest), "The production manifest must contain website CSS, CMS CSS and website JavaScript.")
    artifacts = set()
    for entry in manifest.values():
        require(isinstance(entry, dict) and isinstance(entry.get("file"), str), "Invalid production asset manifest.")
        for name in [entry["file"], *entry.get("css", []), *entry.get("assets", [])]:
            relative = PurePosixPath(name)
            require(not relative.is_absolute() and ".." not in relative.parts, "Unsafe asset path in the production manifest.")
            require((build / relative).is_file(), "A compiled asset referenced by the manifest is missing.")
            artifacts.add(relative.as_posix())
        for reference in [*entry.get("imports", []), *entry.get("dynamicImports", [])]:
            require(reference in manifest, "A manifest import is missing.")
    actual = {path.relative_to(build).as_posix() for path in build.rglob("*") if path.is_file() and path != manifest_path}
    require(actual == artifacts, "The build directory contains obsolete or unreferenced files. Rebuild with npm run build.")
    for name in PUBLIC_REQUIRED:
        require((PUBLIC / name).is_file(), "A required public asset is missing: " + name)
    require((PRIVATE / "storage/app/geoip/country.mmdb").is_file(), "The local country database is missing.")
    require((PRIVATE / "storage/app/geoip/country-version.json").is_file(), "The country database attribution metadata is missing.")
    photos = list((PUBLIC / "assets/photos").glob("*.webp"))
    masters = list((PRIVATE / "storage/app/brand-sources/photos").glob("*.png"))
    require(len(photos) == 39 and len(masters) == 13, "The approved photography set is incomplete.")
    require((PRIVATE / "storage/app/brand-sources/artwork/original-master.svg").is_file(), "The original brand artwork is missing.")
    return len(artifacts), len(photos), len(masters)


def environment_bytes(path):
    require(os.environ.get("GITHUB_ACTIONS") != "true", "Production environment files cannot be included in GitHub Actions artifacts.")
    require(path.is_file() and not path.is_symlink(), "The supplied production environment must be a regular file.")
    value = path.read_bytes()
    text = value.decode("utf-8-sig")
    values = {}
    for line in text.splitlines():
        match = re.match(r"^\s*([A-Za-z_][A-Za-z_0-9]*)\s*=\s*(.*?)\s*$", line)
        if not match:
            continue
        name, field = match.groups()
        require(name not in values, "The environment contains duplicate settings.")
        if len(field) >= 2 and field[0] == field[-1] and field[0] in {"'", '"'}:
            field = field[1:-1]
        values[name] = field
    require(values.get("APP_ENV") == "production", "The included environment must use APP_ENV=production.")
    require(values.get("APP_DEBUG", "").lower() == "false", "The included environment must disable debugging.")
    require(values.get("DB_CONNECTION") == "mysql", "The included environment must retain the MySQL connection.")
    require(all(values.get(name) for name in ("DB_HOST", "DB_DATABASE", "DB_USERNAME", "APP_KEY")), "The included environment is missing an essential connection or encryption setting.")
    require(values.get("APP_URL", "").startswith("https://"), "The production application URL must use HTTPS.")
    require(not any("PREVIEW" in name for name in values), "Preview settings must not be included.")
    key = values["APP_KEY"]
    require(key.startswith("base64:"), "The existing Laravel encryption key must be retained.")
    try:
        decoded_key = base64.b64decode(key[7:], validate=True)
    except ValueError:
        raise RuntimeError("The supplied encryption key is invalid.") from None
    require(len(decoded_key) == 32, "The supplied encryption key must decode to 32 bytes.")
    return value


def write_entry(archive, name, value, mode):
    entry = zipfile.ZipInfo(name)
    entry.create_system = 3
    entry.compress_type = zipfile.ZIP_DEFLATED
    entry.external_attr = mode << 16
    archive.writestr(entry, value, compresslevel=9)


def package(output, env_file):
    require(PRIVATE.is_dir() and PUBLIC.is_dir(), "Both application folders must exist.")
    output = output.resolve()
    require(not output.is_relative_to(PRIVATE) and not output.is_relative_to(PUBLIC), "The output archive must be outside the application folders.")
    package_count = verify_dependencies()
    asset_count, photo_count, master_count = verify_build()
    files = files_under(PRIVATE, selected_private) + files_under(PUBLIC, selected_public)
    files.sort(key=relative_name)
    names = {relative_name(path) for path in files}
    require(len(files) == len(names), "Duplicate application paths cannot be packaged.")
    environment = environment_bytes(env_file) if env_file else None
    if environment is not None:
        names.add("londra-private/.env")
    directories = {"londra-private/", "public_html/"}
    for name in names:
        parent = PurePosixPath(name).parent
        while parent != PurePosixPath("."):
            directories.add(parent.as_posix() + "/")
            parent = parent.parent
    for relative in WRITABLE_DIRECTORIES:
        parent = PurePosixPath("londra-private") / relative
        while parent != PurePosixPath("."):
            directories.add(parent.as_posix() + "/")
            parent = parent.parent
    output.parent.mkdir(parents=True, exist_ok=True)
    descriptor, temporary_name = tempfile.mkstemp(prefix=".londra-package-", suffix=".tmp", dir=output.parent)
    os.close(descriptor)
    temporary = Path(temporary_name)
    try:
        with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9, allowZip64=True) as archive:
            for name in sorted(directories):
                writable = name.startswith("londra-private/storage/") or name == "londra-private/bootstrap/cache/"
                write_entry(archive, name, b"", (stat.S_IFDIR | (0o775 if writable else 0o755)))
            for path in files:
                name = relative_name(path)
                mode = 0o755 if name == "londra-private/artisan" or name.startswith("londra-private/vendor/bin/") else 0o644
                write_entry(archive, name, path.read_bytes(), stat.S_IFREG | mode)
            if environment is not None:
                write_entry(archive, "londra-private/.env", environment, stat.S_IFREG | 0o600)
        with zipfile.ZipFile(temporary) as archive:
            require(archive.testzip() is None, "The generated ZIP failed its integrity check.")
            require({name for name in archive.namelist() if not name.endswith("/")} == names, "The generated ZIP differs from the validated file set.")
            require({PurePosixPath(name).parts[0] for name in archive.namelist()} == {"public_html", "londra-private"}, "The generated ZIP must contain exactly the two deployment roots.")
            require(all("/node_modules/" not in name and "/.phpunit.cache/" not in name for name in archive.namelist()), "Development files were included in the ZIP.")
            require(not any(name.startswith("londra-private/tests/") or name.startswith("londra-private/reports/") for name in archive.namelist()), "Application tests and audit reports were included in the ZIP.")
            require(not any(name.startswith("londra-private/bootstrap/cache/") and not name.endswith("/") for name in archive.namelist()), "Generated bootstrap caches were included in the ZIP.")
        temporary.replace(output)
        with output.open("rb") as stream:
            checksum = hashlib.file_digest(stream, "sha256").hexdigest()
        return {
            "archive": str(output),
            "bytes": output.stat().st_size,
            "sha256": checksum,
            "files": len(names),
            "production_dependencies": package_count,
            "compiled_assets": asset_count,
            "responsive_photographs": photo_count,
            "original_photographs": master_count,
            "production_environment_included": environment is not None,
            "integrity_verified": True,
        }
    finally:
        if temporary.exists():
            temporary.unlink()


def main():
    parser = argparse.ArgumentParser(description="Create the prebuilt Hostinger ZIP from the current locked production source.")
    parser.add_argument("--prepare", action="store_true", help="Create writable directories and remove generated PHP bootstrap caches before rebuilding dependencies.")
    parser.add_argument("--output", type=Path, help="Destination ZIP outside both application folders.")
    parser.add_argument("--env-file", type=Path, help="Explicit production environment for local packaging only; never permitted in CI.")
    arguments = parser.parse_args()
    require(arguments.prepare != bool(arguments.output), "Choose either --prepare or --output.")
    require(not arguments.env_file or bool(arguments.output), "--env-file requires --output.")
    if arguments.prepare:
        prepare()
        print(json.dumps({"writable_directories_ready": True, "generated_bootstrap_cache_cleared": True}))
    else:
        print(json.dumps(package(arguments.output, arguments.env_file), indent=2))


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        print("Packaging failed: " + str(error), file=sys.stderr)
        sys.exit(1)
