<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script>
// Init DataTables otomatis untuk semua .adm-table yang punya data-dt
$(document).ready(function () {
  $(".adm-table[data-dt]").each(function () {
    var opts = $(this).data("dt");
    var defaults = {
      language: {
        search:           "",
        searchPlaceholder: "Cari...",
        lengthMenu:       "Tampilkan _MENU_ data",
        info:             "Menampilkan _START_-_END_ dari _TOTAL_ data",
        infoEmpty:        "Tidak ada data",
        infoFiltered:     "(difilter dari _MAX_ total data)",
        paginate: {
          first:    "«",
          last:     "»",
          next:     "›",
          previous: "‹"
        },
        emptyTable:    "Tidak ada data tersedia",
        zeroRecords:   "Tidak ada data yang cocok"
      },
      pageLength: 10,
      responsive: true,
      dom: "<'d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3'<'dt-buttons'B><'dt-search'f>>" +
           "<'table-responsive'tr>" +
           "<'d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3'<'dt-info'i><'dt-paging'p>>",
      buttons: [
        {
          extend: "csvHtml5",
          text: "<i class='bi bi-download me-1'></i>CSV",
          className: "adm-btn adm-btn-outline adm-btn-sm",
          title: document.title
        },
        {
          extend: "print",
          text: "<i class='bi bi-printer me-1'></i>Print",
          className: "adm-btn adm-btn-outline adm-btn-sm"
        }
      ]
    };

    // Merge opsi dari data-dt attribute
    var merged = $.extend(true, defaults, opts || {});
    $(this).DataTable(merged);
  });
});
</script>
<script>
function openSidebar() {
  document.getElementById('admSidebar').classList.add('open');
  document.getElementById('admOverlay').classList.add('open');
}
function closeSidebar() {
  document.getElementById('admSidebar').classList.remove('open');
  document.getElementById('admOverlay').classList.remove('open');
}
</script>
</body>
</html>