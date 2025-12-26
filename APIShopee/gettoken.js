const axios = require('axios');
const crypto = require('crypto');
const fs = require('fs'); // Modul buat baca/tulis file
const config = require('./config.json');

async function getTokenShopLevel() {
    const path = "/api/v2/auth/token/get";
    const timest = Math.floor(Date.now() / 1000);

    const baseString = config.PARTNER_ID.toString() + path + timest.toString();
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    const url = `${config.HOST}${path}?partner_id=${config.PARTNER_ID}&timestamp=${timest}&sign=${sign}`;

    const body = {
        code: config.AUTH_CODE,
        shop_id: parseInt(config.SHOP_ID),
        partner_id: config.PARTNER_ID
    };

    try {
        const response = await axios.post(url, body, {
            headers: { "Content-Type": "application/json" }
        });
        
        const data = response.data;
        
        console.log("--- TOKEN BERHASIL DIDAPET ---");
        
        // Itung perkiraan waktu expired (Waktu sekarang + detik expire dari Shopee)
        const expireTime = new Date(Date.now() + data.expire_in * 1000).toLocaleString();

        // 1. Update data di memori
        config.ACCESS_TOKEN = data.access_token;
        config.REFRESH_TOKEN = data.refresh_token;
        config.EXPIRED_AT = expireTime; // Kita tambahin info ini biar lo tau kapan mati tokennya

        // 2. Tulis balik ke file config.json
        fs.writeFileSync('./config.json', JSON.stringify(config, null, 2));

        console.log("Access Token  :", data.access_token);
        console.log("Expired Pada  :", expireTime);
        console.log("Status        : config.json Berhasil Diperbarui!");
        
        return data;
    } catch (error) {
        console.error("Gagal ambil token:", error.response ? error.response.data : error.message);
    }
}

getTokenShopLevel();