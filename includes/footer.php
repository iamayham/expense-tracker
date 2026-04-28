        </main>
    </div>
</div>

    <?php $jsVersion = (string) (filemtime(__DIR__ . '/../assets/js/app.js') ?: time()); ?>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= e(url('assets/js/app.js?v=' . $jsVersion)); ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= e(url('sw.js')); ?>').catch(function () {
                    // Ignore service worker registration failures.
                });
            });
        }
    </script>
</body>
</html>
