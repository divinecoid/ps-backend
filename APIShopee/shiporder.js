const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json');

const PATH = "/api/v2/logistics/ship_order";
const ORDER_SN = "251226DG33VAB8"; // Order SN yang sama

// Data dari response get_shipping_parameter sebelumnya
const PICKUP_DATA = {
    address_id: 291202,
    pickup_time_id: "1766912400_11" // Slot pertama dari response sebelumnya
};

async function shipOrder() {
    const timestamp = Math.floor(Date.now() / 1000);
    
    // 1. Generate Signature
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    // 2. Bersihkan Host
    const baseUrl = config.HOST.replace(/\/+$/, "");
    const fullUrl = `${baseUrl}${PATH}`;

    console.log("------------------------------------------");
    console.log("🚢 SHIPPING ORDER (REQUEST PICKUP)");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("🆔 Order SN    :", ORDER_SN);
    console.log("📍 Address ID  :", PICKUP_DATA.address_id);
    console.log("🕒 Time Slot   :", PICKUP_DATA.pickup_time_id);
    console.log("------------------------------------------");

    const requestConfig = {
        method: 'post',
        url: fullUrl,
        params: {
            partner_id: config.PARTNER_ID,
            timestamp: timestamp,
            access_token: config.ACCESS_TOKEN,
            shop_id: config.SHOP_ID,
            sign: sign
        },
        data: {
            order_sn: ORDER_SN,
            // package_number: "", // Kosongkan jika tidak perlu, atau isi jika split order
            pickup: {
                address_id: PICKUP_DATA.address_id,
                pickup_time_id: PICKUP_DATA.pickup_time_id,
                // tracking_number: "" // Opsional, biasanya kosong untuk request pickup
            }
        },
        headers: {
            'Content-Type': 'application/json'
        }
    };

    try {
        const response = await axios.request(requestConfig);
        
        console.log("✅ RESPONSE:");
        console.log(JSON.stringify(response.data, null, 2));

    } catch (error) {
        console.log("❌ ERROR DETECTED");
        if (error.response) {
            console.log("Status Code :", error.response.status);
            console.log("Data        :", JSON.stringify(error.response.data, null, 2));
        } else {
            console.log("Message     :", error.message);
        }
    }
}

shipOrder();
