const axios = require('axios');
const crypto = require('crypto');
const config = require('../config.json');

// 1. Definisikan Path & Variable Package Number
const PATH = "/api/v2/order/get_package_detail";
// Kamu bisa masukkan banyak package_number dipisah koma (maksimal 50)
const PACKAGE_NUMBERS = "OFG220096901209868"; 

async function getPackageDetail() {
    const timestamp = Math.floor(Date.now() / 1000);
    
    // 2. Generate Signature
    // Penting: Urutan baseString harus benar sesuai aturan Shopee v2
    const baseString = `${config.PARTNER_ID}${PATH}${timestamp}${config.ACCESS_TOKEN}${config.SHOP_ID}`;
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    // 3. Bersihkan Host
    const baseUrl = config.HOST.replace(/\/+$/, "");
    const fullUrl = `${baseUrl}${PATH}`;

    console.log("------------------------------------------");
    console.log("📦 GETTING PACKAGE DETAILS");
    console.log("🔗 URL Target  :", fullUrl);
    console.log("🆔 Packages    :", PACKAGE_NUMBERS);
    console.log("------------------------------------------");

    const requestConfig = {
        method: 'get', // Sesuai CURL kamu tadi menggunakan GET
        url: fullUrl,
        params: { 
            partner_id: config.PARTNER_ID,
            timestamp: timestamp,
            access_token: config.ACCESS_TOKEN,
            shop_id: config.SHOP_ID,
            sign: sign,
            package_number_list: PACKAGE_NUMBERS // Masuk ke sini bro
        }
    };

    try {
        const response = await axios.request(requestConfig);
        
        console.log("✅ RESPONSE DETAIL BARANG:");
        // Di sini kamu akan melihat 'item_list' yang berisi info barangnya
        console.log(JSON.stringify(response.data, null, 2));

        // Contoh cara akses nama barang pertama di paket pertama
        if (response.data.response && response.data.response.package_list) {
            const firstPackage = response.data.response.package_list[0];
            console.log("\n💡 Summary Barang:");
            firstPackage.item_list.forEach((item, index) => {
                console.log(`${index + 1}. ${item.item_name} | Qty: ${item.model_quantity_list[0].quantity}`);
            });
        }

    } catch (error) {
        console.log("❌ ERROR DETECTED");
        if (error.response) {
            console.log("Status Code :", error.response.status);
            console.log("Data         :", JSON.stringify(error.response.data, null, 2));
        } else {
            console.log("Message     :", error.message);
        }
    }
}

getPackageDetail();