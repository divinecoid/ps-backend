const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json');

const PATH = "/api/v2/logistics/get_shipping_parameter";
const ORDER_SN = "251227DGWNKEAV"; // Menggunakan Order SN dari contoh sebelumnya

console.log("Starting script...");

async function getShippingParameter() {
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
    console.log("🚚 GETTING SHIPPING PARAMETER");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("🆔 Order SN    :", ORDER_SN);
    console.log("------------------------------------------");

    const requestConfig = {
        method: 'get',
        url: fullUrl,
        params: {
            partner_id: config.PARTNER_ID,
            timestamp: timestamp,
            access_token: config.ACCESS_TOKEN,
            shop_id: config.SHOP_ID,
            sign: sign,
            order_sn: ORDER_SN
        }
    };

    try {
        const response = await axios.request(requestConfig);
        
        console.log("✅ RESPONSE:");
        console.log(JSON.stringify(response.data, null, 2));

        if (response.data.response) {
            const info = response.data.response;
            if (info.pickup) {
                console.log("\n🚛 Pickup Info:");
                console.log(`   - Address ID: ${info.pickup.address_id || "-"}`);
                console.log(`   - Time Slots: ${info.pickup.time_slot_list ? info.pickup.time_slot_list.length : 0} slots available`);
            }
            if (info.dropoff) {
                console.log("\n📦 Dropoff Info:");
                console.log(`   - Branch List: ${info.dropoff.branch_list ? info.dropoff.branch_list.length : 0} branches`);
            }
        }

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

getShippingParameter();
