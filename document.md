const PATH = "/api/v2/order/search_package_list";

Response
{
  "error": "",
  "message": "",
  "response": {
    "packages_list": [
      {
        "order_sn": "2512222T7U7K85",
        "package_number": "OFG220096901209868",
        "logistics_channel_id": 81017,
        "product_location_id": "IDZ",
        "sorting_group": "",
        "is_shipment_arranged": false
      }
    ],
    "pagination": {
      "total_count": 1,
      "more": false,
      "next_cursor": ""
    },
    "sort": {
      "sort_type": 1,
      "ascending": false
    }   
  },
  "request_id": "e3e3e7f346c9360ca8e58044c4d1db00:01000276c5df7b6c:000000455e5e80d2"
}   



Package Detial

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
  "request_id": "e3e3e7f346d89da7db27118f96f64200:0100024c542254ff:0000006eb4efaf1d"
}