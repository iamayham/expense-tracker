        </main>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="<?= e(url('assets/js/app.js')); ?>"></script>
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
