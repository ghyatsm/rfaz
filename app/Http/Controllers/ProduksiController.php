<?php

namespace App\Http\Controllers;

use App\Models\ProduksiModel;
use App\Models\PesananModel;
use App\Models\ProfilModel;
use App\Models\MesinModel;
use App\Models\MesinTrxModel;


class ProduksiController extends Controller
{
    public function __construct()
    {
        $this->ProduksiModel = new ProduksiModel();
        $this->PesananModel = new PesananModel();
        $this->ProfilModel = new ProfilModel();
        $this->MesinModel = new MesinModel();
        $this->MesinTrxModel = new MesinTrxModel();
    }

    public function index()
    {
        $produksi = ProduksiModel::with('pesananmodel')->get();
        $produksi = ProduksiModel::paginate(10);
        return view('v_produksi', compact('produksi'));
    }

    public function add()
    {
        $mesintrx = $this->MesinTrxModel
            ->where('status', 'like', 'Produksi')
            ->orwhere('status', 'like', 'Pemeliharaan')
            ->get();

        $mesin = $this->MesinModel
            ->where('status', 'like', 'aktif')
            ->get();

        foreach ($mesin as $data) {
            foreach ($mesintrx as $datatrx) {
                if ($data->id == $mesintrx->mesin_id) {
                } else {
                    $result = [
                        'mesin_id' => $data->id,
                        'kode_mesin' => $data->kode_mesin,
                        'kecepatan' => $data->kecepatan,
                    ];
                }
            }
        }
        return view('v_addProduksi', compact('result'));
    }

    public function delete($id)
    {
        $this->ProduksiModel->deleteData($id);
        return redirect()->route('produksi')->with('pesan', 'Data Berhasil Terhapus');
    }

    public function insert()
    {
        request()->validate([
            'nama_benang' => 'required',
            'kode_benang' => 'required',
            'harga_benang' => 'required',
        ], [
            'nama_benang.required' => 'wajib diisi',
            'kode_benang.required' => 'wajib diisi',
            'harga_benang.required' => 'wajib diisi'
        ]);

        $data = [
            'nama_benang' => request()->nama_benang,
            'kode_benang' => request()->kode_benang,
            'harga_benang' => request()->harga_benang,
            'created_at' => now(),
            'updated_at' => now()
        ];
        $this->BenangModel->addData($data);
        return redirect()->route('produksi')->with('pesan', 'Data Berhasil Ditambahkan');
    }

    public function edit($id)
    {
        if (!$this->ProduksiModel->detailData($id)) {
            abort(404);
        }
        /*
        Ambil data mesin dengan kondisi:
        - status aktif
        - tidak ada jadwal kecuali untuk produksi yg dipilih
*/
        $result = collect();
        $mesin = $this->MesinModel->where('status', 'like', 'aktif')->get();
        $jadwalmesin = $this->MesinTrxModel
            ->where('status', 'like', 'Produksi')
            ->where('produksi_id', '!=', $id)
            ->orwhere('status', 'like', 'Pemeliharaan')
            ->get();

        $mesin_trx = $this->MesinTrxModel
            ->where('status', 'like', 'Produksi')
            ->where('produksi_id', '=', $id)
            ->get();
        //===== dapatkan mesin yg msh free ======    
        $ada = false;
        foreach ($mesin as $data) {
            foreach ($jadwalmesin as $datatrx) {
                if ($data->id == $datatrx->mesin_id) {
                    $ada = true;
                }
            }
            if (!$ada) {
                $result->add([
                    'mesin_id' => $data->id,
                    'kode_mesin' => $data->kode_mesin,
                    'kecepatan' => $data->kecepatan,
                ]);
            }
            $ada = false;
        }
        //dd($mesin_trx);
        $data = [
            'produksi' => $this->ProduksiModel->detailData($id),
            'mesin' => $result,
            'mesin_trx' => $mesin_trx
        ];
        return view('v_editProduksi', $data);
    }

    public function update($id)
    {
        //== Olah tbl_mesin

        $mesin = $this->MesinModel
            ->where('status', 'like', 'aktif')->get();

        for ($i = 0; $i < $mesin->count(); $i++) {
            if (isset(request()->mesin[$i])) {
                $this->MesinTrxModel->deleteProduksi($id);
            }
        }

        for ($i = 0; $i < $mesin->count(); $i++) {
            if (isset(request()->mesin[$i])) {
                $data = [
                    'produksi_id' => $id,
                    'mesin_id' => request()->mesin[$i],
                    'tgl_mulai' => request()->tgl_mulai,
                    'tgl_selesai' => request()->tgl_selesai,
                    'status' => 'Produksi',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $this->MesinTrxModel->addData($data);
            }
        }

        //== Olah tbl_pesanan dan tbl_produksi
        if (request()->status == "Proses" or request()->status == "Selesai") {

            if (request()->status == "Selesai") {

                $data = [
                    'status' => 'Selesai',
                    'updated_at' => now()
                ];
                $this->MesinTrxModel->updateData(request()->produksi_id, $data);

                //$produksi = $this->ProduksiModel::all()->where('id', $id)->first();
                $data = [
                    'harga_final' => request()->harga_benang * request()->jumlah_produk,
                    'status' => request()->status,
                    'updated_at' => now()
                ];
            } else {

                $data = [
                    'status' => request()->status,
                    'updated_at' => now()
                ];
            }

            $this->PesananModel->updateData($id, $data);
            return redirect()->route('produksi')->with('pesan', request()->pesanan_id . 'Data Berhasil Terupdate');
        } else {

            request()->validate([
                'tgl_mulai' => 'required',
                'tgl_masuk_benang' => 'required',
                'jumlah_benang' => 'required'
            ]);

            $data = [
                'tgl_mulai' => request()->tgl_mulai,
                'tgl_masuk_benang' => request()->tgl_masuk_benang,
                'jumlah_benang' => request()->jumlah_benang,
                'tgl_selesai' => request()->tgl_selesai,
                'jumlah_produk' => request()->jumlah_produk,
                'updated_at' => now()
            ];
            $this->ProduksiModel->updateData($id, $data);

            return redirect()->route('produksi')->with('pesan', 'Data Berhasil Terupdate');
        }
    }

    public function search()
    {
        $produksi = ProduksiModel::with('pesananmodel')
            ->where('kode_pesanan', 'like', '%' . request()->kode_pesanan . '%')
            ->paginate(10);

        return view('v_produksi', compact('produksi'));
    }
}
