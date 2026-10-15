<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia><?php echo e(config('app.name', 'InTouch Connect Automation')); ?></title>

        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
        <!-- 1. The default fallback for older browsers -->
        <link rel="icon" href="/assets/brand/favicon.ico" sizes="any">

        <!-- 2. The modern scalable standard (covers almost all modern desktop/mobile browsers) -->
        <link rel="icon" type="image/svg+xml" href="/assets/brand/favicon.svg">

        <!-- 3. Standard PNG sizes for general browser tabs and bookmarks -->
        <link rel="icon" type="image/png" sizes="16x16" href="/assets/brand/favicon-16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/brand/favicon-32.png">
        <link rel="icon" type="image/png" sizes="48x48" href="/assets/brand/favicon-48.png">

        <!-- 4. Apple devices (iPhones/iPads saved to home screen) -->
        <link rel="apple-touch-icon" sizes="180x180" href="/assets/brand/apple-touch-icon.png">

        <!-- 5. Android devices and Progressive Web Apps (PWA) icons -->
        <link rel="icon" type="image/png" sizes="192x192" href="/assets/brand/favicon-192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/assets/brand/favicon-512.png">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"]); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page, JSON_HEX_TAG); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH C:\xampp\htdocs\social-saas\resources\views/app.blade.php ENDPATH**/ ?>