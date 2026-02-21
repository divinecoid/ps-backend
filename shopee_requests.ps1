Param(
  [string]$BASE_URL = "http://localhost:8000",
  [string]$TOKEN = "",
  [string]$ORDER_SN = "251227DGWNKEAV",
  [string]$ADDRESS_ID = "123456",
  [string]$PICKUP_TIME_ID = "PICKUP_TIME_ID",
  [string]$SHIPPING_DOCUMENT_TYPE = "NORMAL_AIR_WAYBILL"
)

$Headers = @{ "Authorization" = "Bearer $TOKEN"; "Content-Type" = "application/json" }

Invoke-RestMethod -Method Get -Uri "$BASE_URL/api/shopee/shipping-parameter?order_sn=$ORDER_SN" -Headers $Headers

Invoke-RestMethod -Method Post -Uri "$BASE_URL/api/shopee/ship-order" -Headers $Headers -Body (ConvertTo-Json @{
  order_sn = $ORDER_SN
  address_id = $ADDRESS_ID
  pickup_time_id = $PICKUP_TIME_ID
})

Invoke-WebRequest -Method Post -Uri "$BASE_URL/api/shopee/download-shipping-document" -Headers $Headers -Body (ConvertTo-Json @{
  order_sn = $ORDER_SN
  shipping_document_type = $SHIPPING_DOCUMENT_TYPE
}) -OutFile ("shipping_document_{0}.pdf" -f $ORDER_SN)
