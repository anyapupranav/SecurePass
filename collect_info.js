var ipinfoToken = 'b05f33d5c09daa';

// collect_info.js

function getPublicIPInfo() {
    return fetch(`https://ipinfo.io/json?token=${ipinfoToken}`)
        .then(response => response.json())
        .catch(error => {
            console.error('Error fetching IP info:', error);
            return null;
        });
}

function getBrowserDeviceInfo() {
    return {
        browser: platform.name + ' ' + platform.version,
        os: platform.os.family + ' ' + platform.os.version,
        device: platform.product || 'Desktop'
    };
}