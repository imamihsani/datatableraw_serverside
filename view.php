<?php defined('BASEPATH') or exit('No direct script access allowed');
/**
contoh implementasi view
@property format $format
 */
?>
<style>
.toolbar {
    float: left;
}

.table-sm thead tr th {
    text-align: center;
}

.table-sm tfoot tr th {
    padding: 0;
}

.select2-container .select2-selection--single .select2-selection__rendered
 {
    display: block;
    padding-left: 8px;
    padding-right: 20px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-top: -10px;
}

.select2-selection__arrow{
    margin-top: -2px;
}

.select2-selection__rendered {
    line-height: 34px !important;
}

.select2-selection__arrow {
    height: 34px !important;
}

</style>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="card mb-2">
            <!-- <div class="card-body"> -->
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered nowrap" id="data-aset">
                    <thead class="btn-birumsa text-light">
                        <tr>
                            <th class="bg-birumsa">No.</th>
                            <th class="bg-birumsa">Kode Aktiva</th>
                            <th class="bg-birumsa">Kategori</th>
                            <th class="bg-birumsa">Jenis</th>
                            <th class="bg-birumsa">Merk</th>
                            <th class="bg-birumsa">Tipe</th>
                            <th class="bg-birumsa">Serial Number</th>
                            <th class="bg-birumsa">Jumlah</th>
                            <th class="bg-birumsa">Harga Perolehan</th>
                            <th class="bg-birumsa">Tanggal Pembelian</th>
                            <th class="bg-birumsa">Status</th>
                            <th class="bg-birumsa">Kondisi</th>
                            <th class="bg-birumsa">Cabang</th>
                            <th class="bg-birumsa">Detail Lokasi</th>
                            <th class="bg-birumsa">Penanggung Jawab</th>
                        </tr>
                    </thead>

                    <tfoot class="bg-light">
                        <tr>
                            <th>No.</th>
                            <th>Kode Aktiva</th>
                            <th>Kategori</th>
                            <th>Jenis</th>
                            <th>Merk</th>
                            <th>Tipe</th>
                            <th>Serial Number</th>
                            <th>Jumlah</th>
                            <th>Harga Perolehan</th>
                            <th class="text-center">Tanggal Pembelian</th>
                            <th>Status</th>
                            <th>Kondisi</th>
                            <th>Cabang</th>
                            <th>Detail Lokasi</th>
                            <th>Penanggung Jawab</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <!-- </div> -->
        </div>
    </div>
</section>
<!-- /.content -->
<!-- toast  -->
<?php if ($this->session->flashdata('message')): ?>
<div class="position-fixed bottom-0 right-0 p-3" style="z-index: 5; right: 0; bottom: 0;">
    <div id="toast" class="toast bg-success" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2500">
        <div class="toast-body">
            <div class="d-flex justify-content-between align-items-center">
                <div id="toast-body"><?= $this->session->flashdata('message'); ?></div>
                <button type="button" class="pl-2 ml-auto close" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- toast  -->
<!-- /.content-wrapper -->
<script src="<?= base_url(); ?>assets/json/cryptojs-aes.min.js"></script>
<script src="<?= base_url(); ?>assets/json/cryptojs-aes-format.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function(event) {

    const params = new URLSearchParams(window.location.search);
    const jenis = params.get('jenis');
    const cabang = params.get('cabang');

    



    function shortText(text, max = 10) {
        if (!text) return "";

        if (text.length <= max) return text;

        let shorted = text.substring(0, max) + "...";

        // tooltip dengan title=teks asli
        return `<span data-toggle="tooltip" title="${text}">${shorted}</span>`;
    }

    let exclude = [0]; // kolom No dan Aksi tidak ada filter
    let shortenColumn = [6]; // index kolom material ingin dipersingkat

    $('#data-aset tfoot th').each(function(i) {
        var title = $(this).text();

        // Kosongkan dulu footer (WAJIB)
        $(this).empty();

        if (!exclude.includes(i)) {
            $('<input>', {
                    type: 'text',
                    class: 'form-control form-control-sm',
                    placeholder: 'Cari ' + title
                })
                .appendTo(this)
                .on('keyup change clear', function() {
                    data_aset.column(i).search(this.value).draw();
                });
        }
    });

    let data_aset = $("#data-aset").DataTable({
        processing: true,
        serverSide: true,
        pageLength: 50,
        lengthMenu: [
            [50, 100, 150, 200, 300, 400, 800, 1000],
            [50, 100, 150, 200, 300, 400, 800, 1000]
        ],
        responsive: false,
        scrollY: "450px",
        scrollCollapse: true,
        scrollX: true,
        paging: true,
        autoWidth: false,
        fixedHeader: true,
        fixedColumns: {
            leftColumns: 7
        },
        ajax: {
            url: "<?= base_url('common/commondatabaseaset/get_datatable?user_token=' . $user_token) ?>",
            type: "POST",
            data: function(d) {
                // Filter tambahan
                d.cabang = $("#filter_cabang").val() || cabang;
                d.kategori = $("#filter_kategori").val() || jenis;
                d.lokasi = $("#filter_lokasi").val();
                d.kondisi = $("#filter_status").val();
            }
        },
        columns: [{
                data: "no",
                orderable: false,
                searchable: false
            },
            {
                data: 'kode_aktiva',
                render: function(data, type, row) {
                    return `<a href="<?= base_url('ga/gadatabaseasetnew/view?user_token=' . $user_token . '&no=') ?>${row.recid}" target="_aset">${row.kode_aktiva}</a>`;
                }
            },
            {
                data: "kategori"
            },
            {
                data: "jenis"
            },
            {
                data: "merek"
            },
            {
                data: "tipe"
            },
            {
                data: "serial_number"
            },
            {
                data: "jumlah"
            },
            {
                data: 'harga_perolehan',
                className: 'text-right'
            },
            {
                data: 'tanggal_pembelian',
                className: 'text-center'
            },
            {
                data: 'status'
            },
            {
                data: 'kondisi'
            },
            {
                data: 'cabang'
            },
            {
                data: 'detail_lokasi'
            },
            {
                data: "penanggung_jawab"
            }
        ],

        "order": [
            [2, "asc"]
        ],

        dom: '<"row pt-2 px-2"<"col-sm-12 col-md-auto"l><"col-sm-12 col-md"<"#toolbar.row justify-content-md-center">><"col-sm-12 col-md-auto"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function() {
            var api = this.api();

            let filter_cabang = $("#filter_cabang");
            let filter_kategori = $("#filter_kategori");

            // Tambahkan toolbar dropdown filter client & tahun
            $("#toolbar").html(`
                    <select class="select_2_global col-md-4" id="filter_kategori" name="kategori">
                        <option value="all">All Kategori</option>
                        <?php foreach($jenis_aset as $j) : ?>
                            <option><?= $j->jenis; ?></option>
                        <?php endforeach; ?>
                       
                    </select>
                    &nbsp;
                    <select class="select_2_global col-md-2" id="filter_cabang" name="cabang">
                        <option value="all">All Cabang</option>
                        <?php foreach($cabang as $loc) : ?>
                            <option><?= $loc['nama_cabang'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    &nbsp;
                    <select class="select_2_global col-md-2" id="filter_lokasi" name="lokasi">
                        <option value="">All Lokasi</option>
                    </select>
                    &nbsp;
                    <select class="select_2_global col-md-2" id="filter_status" name="status">
                        <option value="">All Status & Kondisi</option>
                        <option value="1">Ada & Baik</option>
                    </select>
                `);

                if (cabang) {
                    $("#filter_cabang").val(cabang).trigger('change.select2');
                }

                if (jenis) {
                    $("#filter_kategori").val(jenis).trigger('change.select2');
                }

            select2global();

            function loadLokasi()
            {
                let cabang = $('#filter_cabang').val();
                let jenis  = $('#filter_kategori').val();

                let $select = $('#filter_lokasi');

                $select.html('<option value="">Sedang mencari data...</option>').trigger('change');

                $.ajax({
                    url: '<?= base_url('common/commondatabaseaset/get_jenisaset?user_token='.$user_token); ?>',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        cabang: cabang,
                        jenis: jenis
                    },
                    success: function (response) {

                        $select.empty();
                        $select.append('<option value="">All Lokasi</option>');

                        if(response.length){
                            $.each(response, function (i, row) {
                                $select.append(`<option value="${row.lokasi}">${row.lokasi}</option>`);
                            });
                        }

                        $select.trigger('change');
                    }
                });
            }

            // trigger saat filter berubah
            $('#filter_cabang, #filter_kategori').on('change', loadLokasi);

            loadLokasi();

            // Trigger reload saat filter diubah
            $("#filter_cabang, #filter_kategori, #filter_lokasi, #filter_status").on('change',
                function() {
                    data_aset.ajax.reload();
                });
        }
    });
    
});
</script>
<!-- toast -->
<script>
$(document).ready(function() {
    $('#toast').toast('show');
});
</script>
