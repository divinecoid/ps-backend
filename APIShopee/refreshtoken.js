const axios = require('axios');
const crypto = require('crypto');
const fs = require('fs');
const config = require('./config.json');

async function refreshAccessToken() {
    const path = "/api/v2/auth/access_token/get";
    const timest = Math.floor(Date.now() / 1000);

    // 1. Generate Signature
    // Rumus: partner_id + path + timestamp
    const baseString = config.PARTNER_ID.toString() + path + timest.toString();
    const sign = crypto.createHmac('sha256', config.PARTNER_KEY)
                       .update(baseString)
                       .digest('hex');

    const url = `${config.HOST}${path}?partner_id=${config.PARTNER_ID}&timestamp=${timest}&sign=${sign}`;

    // 2. Body Payload untuk Refresh Token
    const body = {
        refresh_token: config.REFRESH_TOKEN, // Ambil dari config
        partner_id: config.PARTNER_ID,
        shop_id: parseInt(config.SHOP_ID)
    };

    try {
        const response = await axios.post(url, body, {
            headers: { "Content-Type": "application/json" }
        });

        const data = response.data;

        if (data.error) {
            console.error("Gagal Refresh:", data.message);
            return;
        }

        console.log("--- REFRESH TOKEN BERHASIL ---");

        // Hitung waktu expired baru
        const expireTime = new Date(Date.now() + data.expire_in * 1000).toLocaleString();

        // 3. Update data di memori
        config.ACCESS_TOKEN = data.access_token;
        config.REFRESH_TOKEN = data.refresh_token; // Refresh token juga bisa berubah!
        config.EXPIRED_AT = expireTime;

        // 4. Tulis balik ke file config.json
        fs.writeFileSync('./config.json', JSON.stringify(config, null, 2));

        console.log("Access Token Baru  :", data.access_token);
        console.log("Refresh Token Baru :", data.refresh_token);
        console.log("Expired Pada       :", expireTime);
        console.log("Status             : config.json Berhasil Diperbarui!");

    } catch (error) {
        console.error("Gagal Request Refresh:", error.response ? error.response.data : error.message);
    }
}

refreshAccessToken();