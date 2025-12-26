const crypto = require('crypto');
const config = require('./config.json');

function shopAuth() {
    const path = "/api/v2/shop/auth_partner";
    const timest = Math.floor(Date.now() / 1000);

    // Logic Python: partner_id + path + timest
    const baseString = config.PARTNER_ID.toString() + path + timest.toString();
    
    const sign = crypto
        .createHmac('sha256', config.PARTNER_KEY)
        .update(baseString)
        .digest('hex');

    // Build URL pake data dari JSON
    const url = config.HOST + path + 
                "?partner_id=" + config.PARTNER_ID + 
                "&timestamp=" + timest + 
                "&sign=" + sign + 
                "&redirect=" + encodeURIComponent(config.REDIRECT_URL);

    console.log("--- DEBUG LOG ---");
    console.log("Base String:", baseString);
    console.log("Sign       :", sign);
    console.log("\n--- COPY LINK INI KE BROWSER ---");
    console.log(url);
}

shopAuth();