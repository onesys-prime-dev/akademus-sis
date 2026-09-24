<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title><?= $this->renderSection('title') ?></title>

    <link
        rel="stylesheet"
        href="<?= base_url('assets/css/main.css') ?>">

    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/tokens.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/tokens.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/base.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/base.css') ?>">
    <?= $this->renderSection('pageStyles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/design-system/components.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/design-system/components.css') ?>">
</head>

<body class="bg-light auth-page">

<?= $this->include('components/loading-splash') ?>

<a class="ac-skip-link" href="#mainContent">Skip to content</a>

    <main id="mainContent" tabindex="-1" role="main" class="w-full h-full">
        <?= $this->renderSection('main') ?>
    </main>

<?= $this->renderSection('pageScripts') ?>
</body>
</html>
