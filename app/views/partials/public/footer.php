<footer class="border-top border-secondary mt-auto">
  <div class="container py-4 small text-secondary d-flex flex-wrap gap-2 justify-content-between">
    <div>© <?= date('Y') ?> CINEM4 </div>
    <div class="d-flex gap-3">
      <a class="text-secondary text-decoration-none" href="#">Terms</a>
      <a class="text-secondary text-decoration-none" href="#">Privacy</a>
      <a class="text-secondary text-decoration-none" href="#">Help</a>
    </div>
  </div>
</footer>
<!-- Trailer Modal -->
<div class="modal fade" id="trailerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-dark border-secondary">
      <div class="modal-body p-0 position-relative">

        <button type="button"
          class="btn-close btn-close-white position-absolute end-0 m-3 z-3"
          data-bs-dismiss="modal" aria-label="Close"></button>

        <div class="ratio ratio-16x9">
          <iframe id="trailerFrame"
            src=""
            title="Trailer"
            allow="autoplay; encrypted-media"
            allowfullscreen></iframe>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
  const trailerModal = document.getElementById('trailerModal');
  const trailerFrame = document.getElementById('trailerFrame');

  // klik tombol trailer -> isi iframe
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.watch-trailer');
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const url = btn.getAttribute('data-trailer') || '';
    trailerFrame.src = url ? (url + (url.includes('?') ? '&' : '?') + 'autoplay=1') : '';
  }, true);

  // modal ditutup -> stop video
  trailerModal.addEventListener('hidden.bs.modal', function() {
    trailerFrame.src = '';
  });
</script>
<script>
(() => {
  const els = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window) || els.length === 0) {
    els.forEach(el => el.classList.add('is-in'));
    return;
  }

  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-in');
      obs.unobserve(entry.target); // tampil sekali aja
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -10% 0px'
  });

  els.forEach(el => io.observe(el));
})();
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>