const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json');

const PATH = "/api/v2/logistics/create_shipping_document";

// Data dari hasil request sebelumnya
const ORDER_SN = "251226D0CMX18T";


async function createShippingDocument() {
    const timestamp = Math.floor(Date.now() / 1000);
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    const baseUrl = config.HOST.replace(/\/+$/, "");
    const fullUrl = `${baseUrl}${PATH}`;

    console.log("------------------------------------------");
    console.log("📄 CREATE SHIPPING DOCUMENT");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("🆔 Order SN    :", ORDER_SN);
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
            "order_list": [
                {
                    "order_sn": ORDER_SN
                }
            ]
        }
    };

    console.log("📤 Payload:", JSON.stringify(requestConfig.data, null, 2));

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

createShippingDocument();
