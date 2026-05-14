<?php
function renderPosterCarousel(string $id, array $items, string $mode = 'now')
{
    static $scriptInjected = false;
?>
    <div class="poster-keen-wrap" id="<?= htmlspecialchars($id) ?>-wrap">
        <button type="button"
            class="poster-keen-nav poster-keen-prev"
            aria-label="Previous">
            <i class="bi bi-chevron-left"></i>
        </button>

        <div id="<?= htmlspecialchars($id) ?>" class="keen-slider poster-keen">
            <?php foreach ($items as $m): ?>
                <?php
                $slug    = $m['slug']   ?? '';
                $titleM  = $m['title']  ?? '';
                $poster  = $m['poster'] ?? '';
                $trailer = trim($m['trailer_url'] ?? $m['trailer'] ?? '');

                $age    = trim((string)($m['rating_age'] ?? $m['age'] ?? ''));
                $dur    = trim((string)($m['dur'] ?? (isset($m['duration_minute']) && $m['duration_minute'] > 0 ? (floor($m['duration_minute']/60) > 0 ? floor($m['duration_minute']/60).'h '.($m['duration_minute']%60).'m' : $m['duration_minute'].'m') : '')));
                $format = trim((string)($m['format'] ?? '2D'));
                $rating = trim((string)($m['rating_age'] ?? $m['rating'] ?? ''));
                ?>
                <div class="keen-slider__slide poster-keen-slide">
                    <div class="movie-wrap"
                        role="button"
                        onclick="window.location='movie-detail.php?slug=<?= urlencode($slug) ?>'">

                        <div class="poster-card">
                            <div class="poster-media">
                                <img src="<?= htmlspecialchars($poster) ?>" alt="<?= htmlspecialchars($titleM) ?>">
                            </div>

                            <div class="poster-overlay">
                                <div class="poster-actions">
                                    <?php if ($trailer !== ''): ?>
                                        <button type="button"
                                            class="btn btn-light btn-sm rounded-pill px-3 watch-trailer"
                                            data-bs-toggle="modal"
                                            data-bs-target="#trailerModal"
                                            data-trailer="<?= htmlspecialchars($trailer) ?>">
                                            <i class="bi bi-play-fill me-1"></i> Trailer
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($mode !== 'upcoming'): ?>
                                        <a href="movie-detail.php?slug=<?= urlencode($slug) ?>#jadwal"
                                            class="btn btn-dark btn-sm rounded-pill px-3 btn-ticket">
                                            <i class="bi bi-ticket-perforated me-1"></i> Tiket
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php $hasAnyMeta = ($format !== '' || $rating !== '' || $dur !== ''); ?>
                                <?php if ($hasAnyMeta): ?>
                                    <div class="poster-meta">
                                        <?php if ($format !== ''): ?>
                                            <span class="badge rounded-pill bg-dark text-light border border-secondary">
                                                <?= htmlspecialchars($format) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($rating !== '' && $mode !== 'upcoming'): ?>
                                            <span class="badge rounded-pill bg-danger">
                                                <?= htmlspecialchars($rating) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($dur !== ''): ?>
                                            <span class="badge rounded-pill bg-dark text-light border border-secondary">
                                                <?= htmlspecialchars($dur) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($age !== ''): ?>
                                <div class="poster-badge"><?= htmlspecialchars($age) ?></div>
                            <?php endif; ?>

                            <?php if ($mode === 'upcoming'): ?>
                                <div class="coming-ribbon">COMING SOON</div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-2 fw-semibold text-light text-truncate"><?= htmlspecialchars($titleM) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button"
            class="poster-keen-nav poster-keen-next"
            aria-label="Next">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <?php if (!$scriptInjected): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const keenInstances = {};

                function initPosterKeen(sliderId) {
                    const slider = document.getElementById(sliderId);
                    if (!slider || typeof KeenSlider === 'undefined') return;

                    if (keenInstances[sliderId]) {
                        keenInstances[sliderId].update();
                        return;
                    }

                    const wrap = document.getElementById(sliderId + '-wrap');
                    const prevBtn = wrap ? wrap.querySelector('.poster-keen-prev') : null;
                    const nextBtn = wrap ? wrap.querySelector('.poster-keen-next') : null;

                    const keen = new KeenSlider(slider, {
                        loop: false,
                        mode: 'free-snap',
                        renderMode: 'precision',
                        drag: true,
                        rubberband: false,
                        slides: {
                            origin: 'auto',
                            perView: 5,
                            spacing: 16,
                        },
                        breakpoints: {
                            '(max-width: 1199px)': {
                                slides: {
                                    origin: 'auto',
                                    perView: 4,
                                    spacing: 16
                                },
                            },
                            '(max-width: 991px)': {
                                slides: {
                                    origin: 'auto',
                                    perView: 3,
                                    spacing: 16
                                },
                            },
                            '(max-width: 767px)': {
                                slides: {
                                    origin: 'auto',
                                    perView: 2,
                                    spacing: 14
                                },
                            },
                            '(max-width: 480px)': {
                                slides: {
                                    origin: 'auto',
                                    perView: 2,
                                    spacing: 12
                                },
                            },
                        },
                    });

                    if (prevBtn) prevBtn.addEventListener('click', function() {
                        keen.prev();
                    });
                    if (nextBtn) nextBtn.addEventListener('click', function() {
                        keen.next();
                    });

                    keenInstances[sliderId] = keen;
                }

                document.querySelectorAll('.poster-keen').forEach(function(el) {
                    initPosterKeen(el.id);
                });

                document.querySelectorAll('[data-bs-toggle="pill"], [data-bs-toggle="tab"]').forEach(function(tabBtn) {
                    tabBtn.addEventListener('shown.bs.tab', function() {
                        Object.values(keenInstances).forEach(function(instance) {
                            instance.update();
                        });
                    });
                });

                window.addEventListener('resize', function() {
                    Object.values(keenInstances).forEach(function(instance) {
                        instance.update();
                    });
                });
            });
        </script>
<?php
        $scriptInjected = true;
    endif;
}
?>