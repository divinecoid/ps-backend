# Shopee API Documentation

Berikut adalah dokumentasi Request dan Response untuk beberapa API Shopee yang telah dijalankan.

## 1. Get Order List
Mengambil daftar pesanan berdasarkan rentang waktu dan status.

- **Endpoint**: `/api/v2/order/get_order_list`
- **Method**: `GET`
- **Params**:
  - `time_range_field`: `create_time`
  - `time_from`: [Timestamp 24 jam lalu]
  - `time_to`: [Timestamp sekarang]
  - `page_size`: 20
  - `order_status`: `READY_TO_SHIP`

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "more": false,
    "next_cursor": "",
    "order_list": [
      {
        "order_sn": "251227DGWNKEAV",
        "booking_sn": ""
      },
      {
        "order_sn": "251226DG33VAB8",
        "booking_sn": ""
      }
    ]
  },
  "request_id": "e3e3e7f346de81367be0e461b38bd700:0100029240fcf6ca:00000027c94ad26c"
}
```

---

## 2. Get Order Detail
Mengambil detail lengkap dari satu atau beberapa pesanan.

- **Endpoint**: `/api/v2/order/get_order_detail`
- **Method**: `GET`
- **Params**:
  - `order_sn_list`: `2512222T7U7K85`
  - `response_optional_fields`: `item_list,message_to_seller,buyer_username,total_amount,buyer_address`

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "order_list": [
      {
        "advance_package": false,
        "booking_sn": "",
        "buyer_username": "local_main.id",
        "cod": false,
        "create_time": 1766765653,
        "currency": "IDR",
        "days_to_ship": 2,
        "is_buyer_shop_collection": false,
        "item_list": [
          {
            "add_on_deal": false,
            "add_on_deal_id": 0,
            "consultation_id": "",
            "image_info": {
              "image_url": "https://cf.shopee.co.id/file/id-11134207-7r98o-mik19xbw727y25_tn"
            },
            "is_b2c_owned_item": false,
            "is_prescription_item": false,
            "item_id": 801987320,
            "item_name": "Baju XL Merah",
            "item_sku": "",
            "main_item": false,
            "model_discounted_price": 10000,
            "model_id": 4257087830,
            "model_name": "Merah",
            "model_original_price": 10000,
            "model_quantity_purchased": 1,
            "model_sku": "",
            "order_item_id": 801987320,
            "product_location_id": [
              "IDZ"
            ],
            "promotion_group_id": 0,
            "promotion_id": 0,
            "promotion_type": "",
            "weight": 0.3,
            "wholesale": false
          }
        ],
        "message_to_seller": "",
        "order_sn": "251227DGWNKEAV",
        "order_status": "READY_TO_SHIP",
        "region": "ID",
        "reverse_shipping_fee": 0,
        "ship_by_date": 1766984400,
        "total_amount": 22910,
        "update_time": 1766765654
      },
      {
        "advance_package": false,
        "booking_sn": "",
        "buyer_username": "local_main.id",
        "cod": false,
        "create_time": 1766764761,
        "currency": "IDR",
        "days_to_ship": 2,
        "is_buyer_shop_collection": false,
        "item_list": [
          {
            "add_on_deal": false,
            "add_on_deal_id": 0,
            "consultation_id": "",
            "image_info": {
              "image_url": "https://cf.shopee.co.id/file/id-11134207-7r98o-mik19xbw727y25_tn"
            },
            "is_b2c_owned_item": false,
            "is_prescription_item": false,
            "item_id": 801987320,
            "item_name": "Baju XL Merah",
            "item_sku": "",
            "main_item": false,
            "model_discounted_price": 10000,
            "model_id": 4257087830,
            "model_name": "Merah",
            "model_original_price": 10000,
            "model_quantity_purchased": 1,
            "model_sku": "",
            "order_item_id": 801987320,
            "product_location_id": [
              "IDZ"
            ],
            "promotion_group_id": 0,
            "promotion_id": 0,
            "promotion_type": "",
            "weight": 0.3,
            "wholesale": false
          }
        ],
        "message_to_seller": "",
        "order_sn": "251226DG33VAB8",
        "order_status": "READY_TO_SHIP",
        "region": "ID",
        "reverse_shipping_fee": 0,
        "ship_by_date": 1766984400,
        "total_amount": 22910,
        "update_time": 1766764762
      }
    ]
  },
  "request_id": "e3e3e7f346de87d967156faf2866bd00:0100021bfd6587e7:000000472315a563"
}
```

---

<!-- ## 3. Search Package List
Mencari daftar paket berdasarkan filter tertentu.

- **Endpoint**: `/api/v2/order/search_package_list`
- **Method**: `POST`
- **Body**:
```json
{
  "filter": {
    "package_status": 2,
    "fulfillment_type": 2,
    "invoice_pending": false
  },
  "pagination": {
    "page_size": 100,
    "cursor": ""
  },
  "sort": {
    "sort_type": 1,
    "ascending": false
  }
}
```

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "packages_list": [],
    "pagination": {
      "total_count": 0,
      "more": false,
      "next_cursor": ""
    },
    "sort": {
      "sort_type": 1,
      "ascending": false
    }
  },
  "request_id": "e3e3e7f346d8d69c72aee17c6fdd0400:010002640fda851e:000000100eaaff2f"
}
```

---

## 4. Get Package Detail
Mengambil detail paket berdasarkan nomor paket.

- **Endpoint**: `/api/v2/order/get_package_detail`
- **Method**: `GET`
- **Params**:
  - `package_number_list`: `OFG220096901209868`

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "package_list": [
      {
        "order_sn": "2512222T7U7K85",
        "package_number": "OFG220096901209868",
        "fulfillment_status": "LOGISTICS_INVALID",
        "update_time": 1766692622,
        "tracking_number": "",
        "days_to_ship": 2,
        "recipient_address": {
          "name": "****",
          "phone": "****",
          "town": "",
          "district": "CEMPAKA PUTIH",
          "city": "KOTA JAKARTA PUSAT",
          "state": "DKI JAKARTA",
          "region": "ID",
          "zipcode": "10510",
          "full_address": "****"
        },
        "parcel_chargeable_weight_gram": 0,
        "group_shipment_id": 0,
        "virtual_contact_number": "",
        "package_query_number": "",
        "ship_by_date": 1766656903,
        "tracking_number_expiration_date": 0,
        "item_list": [
          {
            "item_id": 801987320,
            "model_id": 4257087830,
            "item_sku": "",
            "model_sku": "",
            "model_quantity": 2,
            "order_item_id": 801987320,
            "promotion_group_id": 0,
            "product_location_id": "IDZ",
            "consultation_id": ""
          }
        ],
        "logistics_channel_id": 81017,
        "shipping_carrier": "Sandbox-J&T Express(Don't modify)",
        "allow_self_design_awb": true,
        "is_split_up": false,
        "sorting_group": "",
        "is_shipment_arranged": false,
        "status_info_tag": {
          "tag_id": 0,
          "timestamp": 0
        },
        "can_split_order": false,
        "can_unsplit_order": false,
        "is_pre_order": false,
        "is_buyer_shop_collection": false,
        "buyer_proof_of_collection": [],
        "prescription_images": null,
        "pharmacist_name": "",
        "prescription_approval_time": 0,
        "prescription_rejection_time": 0
      }
    ]
  },
  "request_id": "e3e3e7f346d8d72577a1ee49777d1600:01000242d39ae3d5:000000af94472f6a"
}
``` -->

---

## 5. Get Shipping Parameter
Mendapatkan info parameter pengiriman (pickup/dropoff) untuk suatu pesanan.

- **Endpoint**: `/api/v2/logistics/get_shipping_parameter`
- **Method**: `GET`
- **Params**:
  - `order_sn`: `2512222T7U7K85`

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "info_needed": {
      "dropoff": [],
      "pickup": [
        "address_id",
        "pickup_time_id"
      ]
    },
    "pickup": {
      "address_list": [
        {
          "address_id": 291202,
          "region": "ID",
          "state": "DKI JAKARTA",
          "city": "KOTA JAKARTA PUSAT",
          "district": "CEMPAKA PUTIH",
          "town": "",
          "address": "Jalan Sudirman No. 10",
          "zipcode": "10510",
          "address_flag": [
            "default_address",
            "pickup_address",
            "return_address"
          ],
          "time_slot_list": [
            {
              "date": 1766826000,
              "time_text": "08:00 - 09:00",
              "pickup_time_id": "1766826000_3",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "09:00 - 10:00",
              "pickup_time_id": "1766826000_4",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "10:00 - 11:00",
              "pickup_time_id": "1766826000_5",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "11:00 - 12:00",
              "pickup_time_id": "1766826000_6",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "12:00 - 13:00",
              "pickup_time_id": "1766826000_7",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "13:00 - 14:00",
              "pickup_time_id": "1766826000_8",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "14:00 - 15:00",
              "pickup_time_id": "1766826000_9",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "15:00 - 16:00",
              "pickup_time_id": "1766826000_10",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "16:00 - 17:00",
              "pickup_time_id": "1766826000_11",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "17:00 - 18:00",
              "pickup_time_id": "1766826000_12",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "18:00 - 19:00",
              "pickup_time_id": "1766826000_13",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "19:00 - 20:00",
              "pickup_time_id": "1766826000_14",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "20:00 - 21:00",
              "pickup_time_id": "1766826000_15",
              "flags": []
            },
            {
              "date": 1766826000,
              "time_text": "21:00 - 22:00",
              "pickup_time_id": "1766826000_16",
              "flags": []
            },
            {
              "date": 1766912400,
              "time_text": "08:00 - 09:00",
              "pickup_time_id": "1766912400_3",
              "flags": []
            },
            "..."
          ]
        }
      ]
    },
    "dropoff": {
      "branch_list": null
    }
  },
  "request_id": "e3e3e7f346d8e6497b5d0d845f3cf800:010003b477d32797:000000c92cd94a37"
}
```
*Note: `time_slot_list` akan mengembalikan banyak pilihan waktu (jam dan tanggal). Pilih salah satu `pickup_time_id` untuk digunakan di endpoint `ship_order`.*

---

## 6. Ship Order
Melakukan request pickup untuk pesanan.

- **Endpoint**: `/api/v2/logistics/ship_order`
- **Method**: `POST`
- **Body**:
```json
{
  "order_sn": "2512222T7U7K85",
  "pickup": {
    "address_id": 291202,
    "pickup_time_id": "1766826000"
  }
}
```

### Response Example
```json
{
  "error": "",
  "message": "",
  "warning": "",
  "request_id": "e3e3e7f346de946f8580cbaabffc8a00:010003a8a70dd818:0000003ddaec66b8"
}
```
*Note: Response sukses menunjukkan bahwa permintaan pickup telah berhasil dibuat.*

---

## 7. Download Shipping Document
Mendapatkan link download dokumen pengiriman (AWB, dll).

- **Endpoint**: `/api/v2/logistics/download_shipping_document`
- **Method**: `POST`
- **Body**:
```json
{
  "shipping_document_type": "NORMAL_AIR_WAYBILL",
  "order_list": [
    {
      "order_sn": "2512222T7U7K85",
      "package_number": "OFG220096901209868"
    }
  ]
}
```

### Response Example
```json
{
  "error": "logistics.shipping_document_should_print_first",
  "message": "The package should print first. Detail: these orders: 2512222T7U7K85 should print",
  "request_id": "e3e3e7f346db4f0c39a307f18a913a00:0100033aef137286:000000102e16e086"
}
```
*Note: Script telah diupdate untuk menangani response berupa file PDF. Jika sukses, file akan otomatis disimpan sebagai `shipping_document.pdf`. Error di atas terjadi karena pesanan belum diproses cetak di sistem.*

---

## 8. Create Shipping Document Job
Membuat job untuk generate dokumen pengiriman (langkah sebelum download jika diperlukan).

- **Endpoint**: `/api/v2/logistics/create_shipping_document_job`
- **Method**: `POST`
- **Body**:
```json
{
  "shipping_document_type": "THERMAL_LABEL",
  "package_list": [
    "OFG220096901209868"
  ]
}
```

### Response Example
```json
{
  "error": "error_param",
  "message": "Wrong parameters, detail: booking_list is a required field.",
  "request_id": "e3e3e7f346dc212aa0087792f2ede300:010003505f9cd8ea:00000030f00b8be4"
}
```
*Note: Error `booking_list is a required field` muncul ketika mencoba menggunakan `unpackaged_sku_requests`. Ini mungkin karena endpoint yang digunakan (`create_shipping_document_job`) mengharapkan `booking_list` jika tipe dokumen tertentu dipilih, atau ada ketidaksesuaian antara parameter dan tipe dokumen.*

---

## 9. Get Tracking Number
Mendapatkan nomor resi pelacakan untuk paket.

- **Endpoint**: `/api/v2/logistics/get_tracking_number`
- **Method**: `GET`
- **Params**:
  - `order_sn`: `251226D0CMX18T`
  - `package_number`: `OFG220096901209868`
  - `response_optional_fields`: `first_mile_tracking_number`

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "first_mile_tracking_number": null,
    "hint": "",
    "tracking_number": "ID2555553440733U"
  },
  "request_id": "e3e3e7f346dbd79ebfb0f44fdb2b8200:010003bd11ee5044:0000001aa5de9076"
}
```
*Note: Response sukses menampilkan `tracking_number` (resi) yang telah terbentuk.*

---

## 10. Create Shipping Document
Membuat dokumen pengiriman (AWB) secara langsung (alternatif dari job async).

- **Endpoint**: `/api/v2/logistics/create_shipping_document`
- **Method**: `POST`
- **Body**:
```json
{
  "shipping_document_type": "NORMAL_AIR_WAYBILL",
  "order_list": [
    {
      "order_sn": "251226D0CMX18T",
      "package_number": "OFG220096901209868",
      "tracking_number": "ID2555553440733U"
    }
  ]
}
```

### Response Example
```json
{
  "error": "",
  "message": "",
  "response": {
    "result_list": [
      {
        "order_sn": "251226D0CMX18T"
      }
    ]
  },
  "warning": null,
  "request_id": "e3e3e7f346deab2ac75a1b112377f900:010003e123acba9a:000000ee9cfbb9ed"
}
```
*Note: Response sukses menunjukkan bahwa dokumen pengiriman telah berhasil dibuat.*
