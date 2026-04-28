<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$userId = currentUserId();
$errors = [];
$flash = getFlash();

function categoryIcon(string $name): string
{
    $key = strtolower(trim($name));

    $foodIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M7 3v7M10 3v7M7 7h3M16 3v18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M16 3c2.2 1.4 3 3.3 3 5.4V11h-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $billIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M7 3h8l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M15 3v4h4M10 12h4M10 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $transportIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M6 16V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M5 16h14M8 19h.01M16 19h.01M8 16v3M16 16v3M9 10h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $shoppingIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M6 8h12l-1.2 10H7.2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M9 8a3 3 0 1 1 6 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $healthIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.8"/>
</svg>
SVG;

    $entertainmentIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M5 8.5 12 5l7 3.5v7L12 19l-7-3.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="m10 10 4 2-4 2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $educationIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="m4 8 8-4 8 4-8 4z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M8 10v4c0 1.2 1.8 2 4 2s4-.8 4-2v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M20 9v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $coffeeIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M6 10h9v4a4 4 0 0 1-4 4H9a3 3 0 0 1-3-3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M15 11h1a2 2 0 1 1 0 4h-1M8 6v2M11 5v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $petIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M8.5 10.5c1.4-1 5.6-1 7 0 1.7 1.3 2.5 3.4 2 5.3-.4 1.7-1.7 2.7-3.3 2.7-.8 0-1.5-.3-2.2-.8-.7.5-1.4.8-2.2.8-1.6 0-2.9-1-3.3-2.7-.5-1.9.3-4 2-5.3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M8 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM13.5 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM19 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    $tagIcon = <<<'SVG'
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none">
    <path d="M11 4H6a2 2 0 0 0-2 2v5l8.5 8.5a2.1 2.1 0 0 0 3 0l4-4a2.1 2.1 0 0 0 0-3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M7.5 8.5h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
SVG;

    return match (true) {
        str_contains($key, 'food') => $foodIcon,
        str_contains($key, 'bill') => $billIcon,
        str_contains($key, 'transport'), str_contains($key, 'taxi'), str_contains($key, 'bus') => $transportIcon,
        str_contains($key, 'shop') => $shoppingIcon,
        str_contains($key, 'health'), str_contains($key, 'doctor'), str_contains($key, 'pharma') => $healthIcon,
        str_contains($key, 'entertain') => $entertainmentIcon,
        str_contains($key, 'education'), str_contains($key, 'course') => $educationIcon,
        str_contains($key, 'coffee') => $coffeeIcon,
        str_contains($key, 'pet') => $petIcon,
        default => $tagIcon,
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $result = deleteCategoryForUser($userId, (int) ($_POST['category_id'] ?? 0));
        setFlash($result['success'] ? 'success' : 'error', $result['message'] ?? implode(' ', $result['errors'] ?? ['Unable to delete category.']));
        redirect('pages/categories.php');
    }

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $result = saveCategoryForUser($userId, (string) ($_POST['name'] ?? ''), $categoryId);

    if ($result['success']) {
        setFlash('success', $result['message'] ?? 'Category saved successfully.');
        redirect('pages/categories.php');
    }

    $errors = $result['errors'] ?? [];
}

$categories = getUserCategories($userId);
$categoryNames = array_map(
    static fn (array $category): string => strtolower((string) $category['name']),
    $categories
);

$pageTitle = 'Categories';
$pageSubtitle = 'Manage your spending categories';
$pageActions = '<a class="button button-lg" href="#category-form">Add Category</a>';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if ($flash): ?>
    <div
        class="toast-trigger"
        data-toast-message="<?= e($flash['message']); ?>"
        data-toast-type="<?= e($flash['type']); ?>"
        hidden
    ></div>
<?php endif; ?>

<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="true"></div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?= e(implode(' ', $errors)); ?></div>
<?php endif; ?>

<div data-categories-page data-api-url="<?= e(url('api/categories.php')); ?>">
<section class="card section-gap">
    <div id="category-form"></div>
    <div class="card-header-block">
        <h2>Add Category</h2>
        <p class="muted">Create custom categories with a clean structure for better tracking.</p>
    </div>

        <form method="post" id="categoryCreateForm" data-category-form data-existing-names="<?= e(json_encode($categoryNames, JSON_THROW_ON_ERROR)); ?>" data-category-create-form>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="category_id" value="0">

        <div class="form-grid">
            <div class="field full">
                <label for="name">Category Name</label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    placeholder="Enter category name..."
                    value="<?= e($_POST['name'] ?? ''); ?>"
                    required
                >
                <small class="field-note">Use a short, clear label like Food, Travel, or Bills.</small>
            </div>
        </div>

        <div class="category-form-actions">
            <button class="auth-submit" type="submit">Add Category</button>
        </div>
    </form>
</section>

<section class="card section-gap table-card">
    <div class="card-header-block">
        <h2>Categories</h2>
        <p class="muted">Default categories are built-in. Custom categories are editable.</p>
    </div>

    <div class="table-wrap">
        <table class="categories-table">
            <thead>
            <tr>
                <th>Icon</th>
                <th>Name</th>
                <th>Type</th>
                <th class="actions-heading">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $category): ?>
                <tr class="category-row">
                    <td class="category-icon-cell">
                        <span class="category-icon"><?= categoryIcon($category['name']); ?></span>
                    </td>
                    <td>
                        <strong><?= e($category['name']); ?></strong>
                    </td>
                    <td>
                        <?php if ((int) $category['is_default'] === 1): ?>
                            <span class="badge badge-soft-green">Default</span>
                        <?php else: ?>
                            <span class="badge badge-soft">Custom</span>
                        <?php endif; ?>
                    </td>
                    <td class="category-actions">
                        <?php if ((int) $category['is_default'] === 0): ?>
                            <button
                                class="icon-outline-button"
                                type="button"
                                data-category-edit
                                data-category-id="<?= e((string) $category['id']); ?>"
                                data-category-name="<?= e($category['name']); ?>"
                                aria-label="Edit <?= e($category['name']); ?>"
                                title="Edit"
                            >
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 20h4l9.5-9.5-4-4L4.5 16V20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="m12.5 7.5 4 4 1.5-1.5a1.414 1.414 0 0 0 0-2l-2-2a1.414 1.414 0 0 0-2 0L12.5 7.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>

                            <form method="post" class="inline-form" data-category-delete-form>
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="category_id" value="<?= e((string) $category['id']); ?>">
                                <input type="hidden" name="category_name" value="<?= e($category['name']); ?>">
                                <button
                                    class="icon-outline-button icon-outline-danger"
                                    type="button"
                                    data-category-delete
                                    data-category-id="<?= e((string) $category['id']); ?>"
                                    data-category-name="<?= e($category['name']); ?>"
                                    aria-label="Delete <?= e($category['name']); ?>"
                                    title="Delete"
                                >
                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 7h14M10 11v5M14 11v5M8 7l1-2h6l1 2M8 7v11a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<div class="modal-backdrop" id="categoryEditModal" hidden>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="categoryEditTitle">
        <div class="modal-header">
            <div>
                <h3 id="categoryEditTitle">Edit Category</h3>
                <p class="muted">Update the category name and save your changes.</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close edit modal">×</button>
        </div>

        <form method="post" id="categoryEditForm" data-category-form data-existing-names="<?= e(json_encode($categoryNames, JSON_THROW_ON_ERROR)); ?>" data-category-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" id="editCategoryId" value="">
            <input type="hidden" name="original_name" id="editOriginalName" value="">

            <div class="field">
                <label for="editCategoryName">Category Name</label>
                <input id="editCategoryName" type="text" name="name" placeholder="Enter category name..." required>
            </div>

            <div class="modal-actions">
                <button class="button secondary" type="button" data-modal-close>Cancel</button>
                <button class="button" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="categoryDeleteModal" hidden>
    <div class="modal-card modal-card-sm" role="dialog" aria-modal="true" aria-labelledby="categoryDeleteTitle">
        <div class="modal-header">
            <div>
                <h3 id="categoryDeleteTitle">Delete Category</h3>
                <p class="muted">Are you sure you want to delete this category?</p>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Close delete modal">×</button>
        </div>

        <p class="modal-highlight" id="deleteCategoryName"></p>

        <div class="modal-actions">
            <button class="button secondary" type="button" data-modal-close>Cancel</button>
            <button class="button danger" type="button" id="confirmCategoryDelete">Delete</button>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
