#!/usr/bin/env python3
"""Cập nhật đường dẫn require/include sau khi sắp xếp lại includes/."""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

REPOS = {
    "blog-comment-repository.php",
    "blog-repository.php",
    "coupon-admin-repository.php",
    "coupon-repository.php",
    "customer-admin-repository.php",
    "home-repository.php",
    "inventory-repository.php",
    "order-repository.php",
    "product-admin-repository.php",
    "product-repository.php",
    "return-repository.php",
    "review-repository.php",
}

AUTH = {
    "admin-auth.php",
    "customer-auth.php",
    "customer-auth-post.php",
    "auth-login-form.php",
    "auth-register-form.php",
    "auth-modals.php",
    "auth-toast.php",
}

VIEWS = {
    "home.php",
    "catalog.php",
    "product-detail.php",
    "blog.php",
    "blog-detail.php",
    "blog-editor.php",
    "cart.php",
    "checkout.php",
    "account.php",
    "my-orders.php",
    "order-lookup.php",
    "order-detail.php",
    "order-return.php",
    "cart-store.php",
    "coupon-suggestions.php",
    "blog-data.php",
    "back-button.php",
    "site-search.php",
}

ADMIN = {
    "admin-blog-comments.php",
    "admin-blog.php",
    "admin-coupon-form.php",
    "admin-coupons.php",
    "admin-customers.php",
    "admin-dashboard.php",
    "admin-nav.php",
    "admin-order-detail.php",
    "admin-orders.php",
    "admin-post.php",
    "admin-product-form.php",
    "admin-products.php",
    "admin-reviews.php",
    "admin-returns.php",
    "admin-stats.php",
}

HANDLERS = {"storefront-post.php", "blog-editor-handler.php"}

ROOT_INCLUDES = {"helpers.php", "flash.php", "csrf.php", "routes.php", "header.php", "footer.php"}


def target_for(file_path: Path, include_name: str) -> str | None:
    """Return replacement path segment after __DIR__ . '/ for include from file_path."""
    if include_name in REPOS:
        if file_path.parent.name == "repositories":
            return include_name
        if file_path.parent.name == "includes":
            return f"repositories/{include_name}"
        return f"../repositories/{include_name}"
    if include_name in AUTH:
        if file_path.parent.name == "auth":
            return include_name
        if file_path.parent.name == "includes":
            return f"auth/{include_name}"
        return f"../auth/{include_name}"
    if include_name in VIEWS:
        if file_path.parent.name == "views":
            return include_name
        if file_path.parent.name == "includes":
            return f"views/{include_name}"
        return f"../views/{include_name}"
    if include_name in ADMIN:
        if file_path.parent.name == "admin":
            return include_name
        if file_path.parent.name == "includes":
            return f"admin/{include_name}"
        return f"../admin/{include_name}"
    if include_name in HANDLERS:
        if file_path.parent.name == "handlers":
            return include_name
        if file_path.parent.name == "includes":
            return f"handlers/{include_name}"
        return f"../handlers/{include_name}"
    if include_name in ROOT_INCLUDES:
        if file_path.parent.name == "includes":
            return include_name
        return f"../{include_name}"
    if include_name == "../config/database.php" and file_path.parent.name == "repositories":
        return "../../config/database.php"
    return None


def fix_file(path: Path) -> bool:
    text = path.read_text(encoding="utf-8")
    original = text

    def repl(m: re.Match[str]) -> str:
        include_name = m.group(1)
        new = target_for(path, include_name)
        if new is None:
            return m.group(0)
        return f"__DIR__ . '/{new}'"

    text = re.sub(
        r"__DIR__\s*\.\s*'/([^']+)'",
        repl,
        text,
    )

    if path.name == "routes.php":
        for name in VIEWS:
            text = text.replace(f"'includes/{name}'", f"'includes/views/{name}'")
        for name in ADMIN:
            text = text.replace(f"'includes/{name}'", f"'includes/admin/{name}'")

    if path.name == "app.php" and "bootstrap" in str(path):
        subs = {
            "includes/customer-auth.php": "includes/auth/customer-auth.php",
            "includes/customer-auth-post.php": "includes/auth/customer-auth-post.php",
            "includes/admin-auth.php": "includes/auth/admin-auth.php",
            "includes/inventory-repository.php": "includes/repositories/inventory-repository.php",
            "includes/admin-post.php": "includes/admin/admin-post.php",
            "includes/storefront-post.php": "includes/handlers/storefront-post.php",
            "includes/blog-editor-handler.php": "includes/handlers/blog-editor-handler.php",
            "includes/order-repository.php": "includes/repositories/order-repository.php",
            "includes/review-repository.php": "includes/repositories/review-repository.php",
            "includes/return-repository.php": "includes/repositories/return-repository.php",
            "includes/blog-comment-repository.php": "includes/repositories/blog-comment-repository.php",
            "includes/blog-repository.php": "includes/repositories/blog-repository.php",
        }
        for old, new in subs.items():
            text = text.replace(old, new)

    if path.name == "index.php":
        text = text.replace(
            "includes/back-button.php",
            "includes/views/back-button.php",
        )

    if path.parent.name == "api":
        for repo in REPOS:
            text = text.replace(
                f"includes/{repo}",
                f"includes/repositories/{repo}",
            )
        for view in ("cart-store.php",):
            text = text.replace(
                f"includes/{view}",
                f"includes/views/{view}",
            )
        for auth in ("customer-auth.php",):
            text = text.replace(
                f"includes/{auth}",
                f"includes/auth/{auth}",
            )

    if path.name == "export-test-report.bat":
        text = text.replace("docs\\generate-test-report.php", "docs\\testing\\generate-test-report.php")
        text = text.replace("docs\\bao-cao-kiem-thu", "docs\\testing\\bao-cao-kiem-thu")
        text = text.replace("docs\\Winsum-Test-Case", "docs\\testing\\Winsum-Test-Case")

    if path.name == "seed-coupons.sql" or path.name == "migrate-coupon-role.sql":
        text = text.replace("docs/migrate-coupon-role.sql", "docs/sql/migrate-coupon-role.sql")
        text = text.replace("docs/seed-coupons.sql", "docs/sql/seed-coupons.sql")

    if text != original:
        path.write_text(text, encoding="utf-8")
        return True
    return False


def main() -> None:
    changed = []
    patterns = ["**/*.php", "**/*.bat", "**/*.sql"]
    for pattern in patterns:
        for path in ROOT.glob(pattern):
            if "vendor" in path.parts or ".git" in path.parts:
                continue
            if fix_file(path):
                changed.append(str(path.relative_to(ROOT)))
    print(f"Updated {len(changed)} files")
    for p in sorted(changed):
        print(f"  {p}")


if __name__ == "__main__":
    main()
