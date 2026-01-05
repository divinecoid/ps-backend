const axios = require('axios');
const crypto = require('crypto');
const fs = require('fs');
const config = require('./config.json');

const PATH = "/api/v2/logistics/download_shipping_document";

// Data dari hasil request sebelumnya
const ORDER_SN = "251226D0CMX18T";
const PACKAGE_NUMBER = "ID2555553440733U";


async function downloadShippingDocument() {
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
    console.log("📄 DOWNLOAD SHIPPING DOCUMENT");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("🆔 Order SN    :", ORDER_SN);
    console.log("📦 Package No  :", PACKAGE_NUMBER);
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
            shipping_document_type: "NORMAL_AIR_WAYBILL", 
            order_list: [
                {
                    order_sn: ORDER_SN
                }
            ]
        },
        responseType: 'arraybuffer' // Penting untuk handle file binary (PDF)
    };

    try {
        const response = await axios.request(requestConfig);
        
        console.log("✅ RESPONSE RECEIVED");
        
        const contentType = response.headers['content-type'];
        console.log("Content-Type:", contentType);

        if (contentType && contentType.includes('application/json')) {
            // Jika response adalah JSON (kemungkinan error atau metadata)
            const jsonResponse = JSON.parse(response.data.toString());
            console.log(JSON.stringify(jsonResponse, null, 2));
        } else {
            // Jika response adalah file (PDF)
            const fileName = "shipping_document.pdf";
            fs.writeFileSync(fileName, response.data);
            console.log(`✅ File berhasil disimpan sebagai: ${fileName}`);
        }

    } catch (error) {
        console.log("❌ ERROR DETECTED");
        if (error.response) {
            console.log("Status Code :", error.response.status);
            // Coba parse data error jika berupa JSON buffer
            try {
                const errorData = JSON.parse(error.response.data.toString());
                console.log("Data        :", JSON.stringify(errorData, null, 2));
            } catch (e) {
                console.log("Data        :", error.response.data.toString());
            }
        } else {
            console.log("Message     :", error.message);
        }
    }
}

downloadShippingDocument();
