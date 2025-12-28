const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json'); // Load config dari folder yang sama

const PATH = "/api/v2/order/get_order_list";

async function getOrderList() {
    const timestamp = Math.floor(Date.now() / 1000);
    
    // 1. Generate Signature
    // Rumus: partner_id + path + timestamp + access_token + shop_id
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    // 2. Tentukan Parameter Query
    const params = {
        partner_id: config.PARTNER_ID,
        timestamp: timestamp,
        access_token: config.ACCESS_TOKEN,
        shop_id: config.SHOP_ID,
        sign: sign,
        time_range_field: "create_time",
        time_from: timestamp - (24 * 60 * 60), // Ambil data 24 jam terakhir
        time_to: timestamp,
        page_size: 100,
        cursor: 0,
        order_status: "READY_TO_SHIP"
    };

    try {
        const response = await axios.get(`${config.HOST}${PATH}`, { params });
        
        if (response.data.error) {
            console.log("Shopee Error:", response.data.message);
        } else {
            console.log("Data Pesanan:", JSON.stringify(response.data, null, 2));
        }
    } catch (error) {
        console.error("Network/Server Error:", error.response ? error.response.data : error.message);
    }
}

getOrderList();