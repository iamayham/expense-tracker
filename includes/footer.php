        </main>
    </div>
</div>

    <?php $jsVersion = (string) (filemtime(__DIR__ . '/../assets/js/app.js') ?: time()); ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= e(url('assets/js/app.js?v=' . $jsVersion)); ?>"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= e(url('service-worker.js')); ?>').catch(function () {
                    // Ignore registration errors in unsupported/local setups.
                });
            });
        }
    </script>
</body>
</html>
