<section class="w-full space-y-5">
    <header>
        <p class="text-sm font-semibold uppercase tracking-widest text-primary"><?= e(t('Access management')) ?></p>
        <h1 class="mt-2 text-3xl font-bold"><?= e(t($title)) ?></h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-300"><?= e(t('Create or update a user account.')) ?></p>
    </header>

    <?php if ($error): ?>
        <p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"><?= e(t($error)) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="grid gap-5 rounded-xl bg-white p-5 shadow-sm ring-1 ring-zinc-200 sm:grid-cols-2 sm:p-7 xl:grid-cols-3 dark:bg-dark-card dark:ring-zinc-800">
        <input type="hidden" name="_csrf_token" value="<?= e($csrfToken) ?>">

        <div>
            <label for="full_name" class="mb-1.5 block text-sm font-medium"><?= e(t('Full name')) ?></label>
            <input id="full_name" name="full_name" required maxlength="120" autocomplete="name" placeholder="<?= e(t('e.g. Sokha Chan')) ?>" value="<?= e($person['full_name'] ?? '') ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
        </div>

        <div>
            <label for="username" class="mb-1.5 block text-sm font-medium"><?= e(t('Username')) ?></label>
            <input id="username" name="username" required minlength="3" maxlength="60" pattern="[A-Za-z0-9_.-]+" autocomplete="username" placeholder="<?= e(t('e.g. sokha.staff')) ?>" value="<?= e($person['username'] ?? '') ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium"><?= e(t('Email')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></label>
            <input id="email" name="email" type="email" maxlength="150" autocomplete="email" placeholder="<?= e(t('e.g. sokha@example.com')) ?>" value="<?= e($person['email'] ?? '') ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
        </div>

        <div>
            <label for="gender" class="mb-1.5 block text-sm font-medium"><?= e(t('Gender')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></label>
            <select id="gender" name="gender" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <option value="" <?= empty($person['gender']) ? 'selected' : '' ?>><?= e(t('Select gender')) ?></option>
                <option value="female" <?= ($person['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= e(t('Female')) ?></option>
                <option value="male" <?= ($person['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= e(t('Male')) ?></option>
                <option value="other" <?= ($person['gender'] ?? '') === 'other' ? 'selected' : '' ?>><?= e(t('Other')) ?></option>
                <option value="prefer_not_to_say" <?= ($person['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>><?= e(t('Prefer not to say')) ?></option>
            </select>
        </div>

        <div>
            <label for="role" class="mb-1.5 block text-sm font-medium"><?= e(t('Role')) ?></label>
            <select id="role" name="role" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <option value="staff" <?= ($person['role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>><?= e(t('Staff')) ?></option>
                <?php if (($user['role'] ?? '') === 'admin' || ($person['role'] ?? '') === 'admin'): ?>
                    <option value="admin" <?= ($person['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= e(t('Admin')) ?></option>
                <?php endif; ?>
            </select>
        </div>

        <fieldset class="sm:col-span-2 xl:col-span-3">
            <legend class="mb-1.5 text-sm font-medium"><?= e(t('Profile image')) ?> <span class="font-normal text-zinc-500"><?= e(t('(optional)')) ?></span></legend>
            <p class="mb-3 text-xs text-zinc-500 dark:text-zinc-400"><?= e(t('Choose an image file or provide an image URL. JPEG, PNG, WebP, or GIF; maximum 5 MB.')) ?></p>
            <?php $profileImage = (string) ($person['profile'] ?? ''); $profileImageIsUrl = preg_match('~^https?://~i', $profileImage) === 1 && filter_var($profileImage, FILTER_VALIDATE_URL) !== false; $profileImageIsLocal = preg_match('~^/uploads/profiles/[a-f0-9]{32}\.(?:jpg|png|webp|gif)$~', $profileImage) === 1; ?>
            <?php if ($profileImageIsUrl || $profileImageIsLocal): ?><div class="mb-4 flex items-center gap-3"><img src="<?= e($profileImage) ?>" alt="<?= e(t('Current profile image')) ?>" class="size-16 rounded-full border border-zinc-200 object-cover dark:border-zinc-700"><span class="text-sm text-zinc-600 dark:text-zinc-300"><?= e(t('Current profile image')) ?></span></div><?php endif; ?>
            <p class="mb-3 text-sm font-medium"><?= e(t('Upload an image or use a URL')) ?></p>
            <div class="grid max-w-4xl gap-4 md:grid-cols-2">
                <div>
                    <label for="profile_image" class="mb-1.5 block text-sm font-medium"><?= e(t('Choose image file')) ?></label>
                    <input id="profile_image" name="profile_imagThe Staff Dashboard displays only personal statistics; however, this does not mean that a staff member cannot access orders created by others. In a real-world laundry shop, a staff member might need to process payments or hand over garments for orders created by a colleague. Therefore, operational access should be granted based on roles, while personal reports must be filtered by Staff ID.e" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 file:mr-3 file:rounded-md file:border-0 file:bg-violet-50 file:px-3 file:py-1.5 file:font-semibold file:text-primary dark:border-zinc-700 dark:bg-zinc-900">
                </div>
                <div>
                <label for="profile_url" class="mb-1.5 block text-sm font-medium"><?= e(t('Image URL')) ?></label>
                    <input id="profile_url" name="profile_url" type="url" maxlength="2048" placeholder="https://example.com/profile.jpg" value="<?= e($person['profile_url'] ?? '') ?>" class="h-12 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                </div>
            </div>
            <?php if ($profileImage !== ''): ?><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_profile_image" value="1" <?= !empty($person['remove_profile_image']) ? 'checked' : '' ?> class="rounded border-zinc-300 text-primary focus:ring-primary"> <?= e(t('Remove current image')) ?></label><?php endif; ?>
        </fieldset>

        <?php if ($action === '/users/create'): ?>
            <div class="sm:col-span-2 xl:col-span-3">
                <label for="password" class="mb-1.5 block text-sm font-medium"><?= e(t('Initial password')) ?></label>
                <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required placeholder="<?= e(t('At least 12 characters')) ?>" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="mt-1 text-xs text-zinc-500"><?= e(t('Use at least 12 characters. The password is securely hashed before storage.')) ?></p>
            </div>
        <?php endif; ?>

        <div class="flex justify-end gap-3 border-t border-zinc-100 pt-5 sm:col-span-2 xl:col-span-3 dark:border-zinc-800">
            <a href="/users" class="rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold dark:border-zinc-700"><?= e(t('Cancel')) ?></a>
            <button class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover" type="submit"><?= e(t('Save user')) ?></button>
        </div>
    </form>
</section>
