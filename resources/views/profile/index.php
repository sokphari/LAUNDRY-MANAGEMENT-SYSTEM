<?php
$flash = is_array($flash ?? null) ? $flash : null;
$image = (string) ($person['profile'] ?? '');
$safeImage = preg_match('~\A/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $image) === 1
    || (preg_match('~^https?://~i', $image) === 1 && filter_var($image, FILTER_VALIDATE_URL) !== false);
?>
<section class="w-full space-y-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('Account')) ?></p>
        <h1 class="mt-2 text-3xl font-bold"><?= e(t('My Profile')) ?></h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-300"><?= e(t('Update your personal information and profile image.')) ?></p>
    </header>

    <?php if ($flash !== null): ?><p role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"><?= e(t($flash['message'] ?? 'Profile updated successfully.')) ?></p><?php endif; ?>
    <?php if ($error): ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"><?= e(t($error)) ?></p><?php endif; ?>

    <form method="post" action="/profile" enctype="multipart/form-data" class="space-y-6 rounded-xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 sm:p-7 dark:bg-dark-card dark:ring-zinc-800">
        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">
        <div class="flex items-center gap-4">
            <?php if ($safeImage): ?>
                <img src="<?= e($image) ?>" alt="<?= e(t('Current profile image')) ?>" class="size-20 rounded-full border border-zinc-200 object-cover dark:border-zinc-700">
            <?php else: ?>
                <span class="grid size-20 place-items-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300" role="img" aria-label="<?= e(t('No profile image')) ?>"><svg class="size-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 20c.5-3.8 3.1-5.8 7-5.8s6.5 2 7 5.8"/></svg></span>
            <?php endif; ?>
            <div><p class="font-semibold"><?= e($person['full_name'] ?? '') ?></p><p class="text-sm text-zinc-500 dark:text-zinc-400">@<?= e($person['username'] ?? '') ?></p></div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="full_name" class="mb-1.5 block text-sm font-medium"><?= e(t('Full name')) ?></label>
                <input id="full_name" name="full_name" required maxlength="120" autocomplete="name" placeholder="<?= e(t('e.g. Sokha Chan')) ?>" value="<?= e($person['full_name'] ?? '') ?>" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-900">
            </div>
            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium"><?= e(t('Username')) ?></label>
                <input id="username" value="<?= e($person['username'] ?? '') ?>" readonly class="h-12 w-full rounded-lg border border-zinc-300 bg-zinc-100 px-3 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                <p class="mt-1 text-xs text-zinc-500"><?= e(t('Contact an administrator to change your username.')) ?></p>
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium"><?= e(t('Email')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></label>
                <input id="email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="<?= e(t('e.g. sokha@example.com')) ?>" value="<?= e($person['email'] ?? '') ?>" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-900">
            </div>
            <div>
                <label for="gender" class="mb-1.5 block text-sm font-medium"><?= e(t('Gender')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></label>
                <select id="gender" name="gender" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="" <?= empty($person['gender']) ? 'selected' : '' ?>><?= e(t('Select gender')) ?></option>
                    <option value="female" <?= ($person['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= e(t('Female')) ?></option>
                    <option value="male" <?= ($person['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= e(t('Male')) ?></option>
                    <option value="other" <?= ($person['gender'] ?? '') === 'other' ? 'selected' : '' ?>><?= e(t('Other')) ?></option>
                    <option value="prefer_not_to_say" <?= ($person['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>><?= e(t('Prefer not to say')) ?></option>
                </select>
            </div>
        </div>

        <fieldset>
            <legend class="text-sm font-medium"><?= e(t('Profile image')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></legend>
            <p class="mb-3 mt-1 text-xs text-zinc-500 dark:text-zinc-400"><?= e(t('Upload an image or use a URL')) ?>. <?= e(t('Choose either an image file or an image URL, not both.')) ?> JPEG, PNG, WebP, or GIF; maximum 5 MB.</p>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label for="profile_image" class="mb-1.5 block text-sm font-medium"><?= e(t('Choose image file')) ?></label><input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1.5 file:font-semibold file:text-primary dark:border-zinc-700 dark:bg-zinc-900"></div>
                <div><label for="profile_url" class="mb-1.5 block text-sm font-medium"><?= e(t('Image URL')) ?></label><input id="profile_url" name="profile_url" type="url" maxlength="2048" placeholder="https://example.com/profile.jpg" value="<?= e($person['profile_url'] ?? '') ?>" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 dark:border-zinc-700 dark:bg-zinc-900"></div>
            </div>
            <?php if ($image !== ''): ?><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_profile_image" value="1" <?= !empty($person['remove_profile_image']) ? 'checked' : '' ?> class="rounded border-zinc-300 text-primary focus:ring-primary"> <?= e(t('Remove current image')) ?></label><?php endif; ?>
        </fieldset>

        <div class="flex justify-end border-t border-zinc-100 pt-5 dark:border-zinc-800"><button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover"><?= e(t('Save profile')) ?></button></div>
    </form>
</section>
