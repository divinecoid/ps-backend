const axios = require('axios');
const crypto = require('crypto');
const config = require('./config.json');

// Cek kembali apakah PATH sudah benar (tanpa typo)
const PATH = "/api/v2/order/search_package_list";

async function getSearchPackageList() {
    const timestamp = Math.floor(Date.now() / 1000);
    
    // 1. Generate Signature
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY).update(baseString).digest('hex');

    // 2. Bersihkan Host (Hapus slash di akhir jika ada)
    const baseUrl = config.HOST.replace(/\/+$/, "");
    const fullUrl = `${baseUrl}${PATH}`;

    console.log("------------------------------------------");
    console.log("🛠️  SWITCHING TO POST METHOD");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("------------------------------------------");

    const requestConfig = {
        method: 'post', // UBAH KE POST
        url: fullUrl,
        params: { // Common parameters tetap di Query String
            partner_id: config.PARTNER_ID,
            timestamp: timestamp,
            access_token: config.ACCESS_TOKEN,
            shop_id: config.SHOP_ID,
            sign: sign
        },
        data: { // Request body
            filter: {
                package_status: 2,
                fulfillment_type: 2,
                invoice_pending: false
            },
            pagination: { page_size: 100, cursor: "" },
            sort: { sort_type: 1, ascending: false }
        },
        headers: { 'Content-Type': 'application/json' }
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

getSearchPackageList();