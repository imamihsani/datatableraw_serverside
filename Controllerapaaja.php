<?php
//contoh
public function get_datatable()
    {
        $db2 = $this->load->database('old_db', TRUE);

        $cabang = $this->input->post('cabang');
        $kategori = $this->input->post('kategori');

        if ($cabang == "all") {
            $cabang = "";
        }

        if ($kategori == "all") {
            $kategori = "";
        }

        $lokasi = $this->input->post('lokasi');
        $kondisi = $this->input->post('kondisi');

        $sql = $this->ga->getAsetDatatable($cabang, $kategori, $lokasi, $kondisi);


        $dt = new DatatableRaw($db2);

        $result = $dt
            ->setBaseQuery($sql)
            ->setColumnMap([
                'no' => 'a.no',
                'kode_aktiva' => 'a.kode_aktiva',
                'kategori' => 'a.`Pengelompokan Aktiva Tetap`',
                'jenis' => 'a.`Jenis Aktiva Tetap`',
                'merek' => 'a.`Merek`',
                'tipe' => 'a.`Tipe`',
                'serial_number' => 'a.`Serial Number`',
                'cabang' => 'a.`Cabang`',
                'detail_lokasi' => 'a.`Detail Lokasi`',
                'jumlah' => 'a.`Jumlah`',
                'status' => 'a.`Status`',
                'kondisi' => 'a.`Kondisi Aktiva`',
                'harga_perolehan' => 'a.`Harga Perolehan`',
                'tanggal_pembelian' => 'a.`Tanggal Pembelian`',
                'penanggung_jawab' => 'b.nama'
            ])
            ->edit_column('harga_perolehan', function ($value) {
                return number_format($value, 0, '.', '.');
            })
            ->edit_column('tanggal_pembelian', function ($value) {
                return date('d/m/Y', strtotime($value));
            })
            ->showIndex()
            ->generate();

        json_response($result, 200);
    }
