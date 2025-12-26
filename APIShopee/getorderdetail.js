const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json');

const PATH = "/api/v2/order/get_order_detail";
const ORDER_SN_LIST = "251227DGWNKEAV,251226DG33VAB8"; 

async function getOrderDetail() {
    const timestamp = Math.floor(Date.now() / 1000);
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    const baseUrl = config.HOST.replace(/\/+$/, "");

    const requestConfig = {
        method: 'get',
        url: `${baseUrl}${PATH}`,
        params: {
            partner_id: config.PARTNER_ID,
            timestamp: timestamp,
            access_token: config.ACCESS_TOKEN,
            shop_id: config.SHOP_ID,
            sign: sign,
            order_sn_list: ORDER_SN_LIST,
            // Ditambah buyer_address jika ingin tahu lokasi kirim
            response_optional_fields: 'item_list,message_to_seller,buyer_username,total_amount,buyer_address'
        }
    };

    try {
        const response = await axios.request(requestConfig);
        
        // --- TAMPILKAN FULL RESPONSE DI SINI ---
        console.log("================ FULL JSON RESPONSE ================");
        console.log(JSON.stringify(response.data, null, 2));
        console.log("====================================================");

        const orders = response.data.response.order_list;

        if (orders && orders.length > 0) {
            orders.forEach(order => {
                console.log(`🆔 No. Pesanan : ${order.order_sn}`);
                console.log(`👤 Pembeli     : ${order.buyer_username}`);
                console.log(`✉️  Note Buyer  : ${order.message_to_seller || "-"}`);
                console.log("📦 Item:");
                order.item_list.forEach((item, i) => {
                    console.log(`   ${i + 1}. ${item.item_name} [${item.model_name}] x${item.model_quantity_purchased}`);
                });
            });
        }
    } catch (error) {
        console.log("❌ ERROR:", error.response ? error.response.data : error.message);
    }
}

getOrderDetail();