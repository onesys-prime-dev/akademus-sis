<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademus SIS | <?= esc($pageTitle) ?></title>

    <link rel="stylesheet" href="<?= base_url('assets/css/main.css') ?>">
    
    <?= $this->renderSection('styles') ?>
</head>
<body>

    <div class="layout">

        <!-- Sidebar -->
        <aside class="layout__sidebar">
            <?= $this->include('layouts/sidebar', [
                'activePage' => $activePage ?? ''
            ]) ?>
        </aside>
        
        <!-- Main Area -->
        <div class="layout__main">

            <!-- Header -->
            <header class="layout__header">
                <?= $this->include('layouts/header') ?>
            </header>

            <!-- Page Content -->
            <main class="layout__content">
                <?= $this->renderSection('content') ?>
            </main>

        </div>

    </div>

    <!-- Page Scripts -->
    <?= $this->renderSection('scripts') ?>

</body>
</html>