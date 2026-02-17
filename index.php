<?php
/**
 * QUÂN ĐOÀN PRO - RENDER WEB DEPLOY
 */

// Tạo một Server HTTP đơn giản để Render không tắt bot
if (php_sapi_name() !== 'cli') {
    header("Content-Type: text/plain");
    echo "Bot is Running 24/7!";
    exit;
}

$js_logic = <<<'JS'
const { Client, SpotifyRPC } = require('discord.js-selfbot-v13');
const { joinVoiceChannel, createAudioPlayer, createAudioResource, NoSubscriberBehavior, AudioPlayerStatus, StreamType, getVoiceConnection } = require('@discordjs/voice');
const fs = require('fs');
const { spawn } = require('child_process');
const http = require('http');

// MỞ PORT ĐỂ TREO TRÊN RENDER
const port = process.env.PORT || 3000;
http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Quan Doan Pro is Online');
}).listen(port, () => console.log(`🌍 Web Server chạy tại port: ${port}`));

if (typeof File === 'undefined') { global.File = class {}; }

const Prefix = "+*";
let isSpamming = false;
let isLoopingMic = false;
let spamInterval = null;

const spotifyConfig = {
    song: "Alo Vũ À Vũ", 
    artist: "Độ Mixi",
    album: "Nguyễn Mẹ Mày",
    imageID: "ab67616d0000b27393452ef2e3d373e4404740e2", 
    days: 2500
};

const loadTokens = () => {
    try {
        if (!fs.existsSync('tokens.txt')) fs.writeFileSync('tokens.txt', '');
        return fs.readFileSync('tokens.txt', 'utf8').split(/\r?\n/).map(t => t.trim()).filter(t => t.length > 20);
    } catch (e) { return []; }
};

let tokens = loadTokens();
let clients = [];

function setSpotifyStatus(client) {
    try {
        const spotify = new SpotifyRPC(client)
            .setAssetsLargeImage(`spotify:${spotifyConfig.imageID}`)
            .setAssetsLargeText(spotifyConfig.album)
            .setState(spotifyConfig.artist)
            .setDetails(spotifyConfig.song)
            .setStartTimestamp(Date.now() - (spotifyConfig.days * 86400 * 1000))
            .setEndTimestamp(Date.now() + 3600000);
        
        client.user.setPresence({ activities: [spotify.toJSON()] });
    } catch (err) {}
}

const initArmy = () => {
    clients.forEach(c => { try { c.destroy(); } catch(e) {} });
    clients = [];
    tokens = loadTokens();
    tokens.forEach((token) => {
        const client = new Client({ checkUpdate: false });
        client.login(token).catch(() => {});
        client.on('ready', () => {
            setSpotifyStatus(client);
            console.log(`✅ [LÍNH] ${client.user.username} - READY`);
        });
        clients.push(client);
    });
};

initArmy();

function createAppoResource(filePath) {
    const ffmpeg = spawn('ffmpeg', [
        '-i', filePath,
        '-filter:a', 'volume=65dB, acompressor=threshold=-10dB:ratio=20:attack=5:release=50, bass=g=25:f=110, alimiter=limit=0.9:level=1',
        '-f', 's16le', '-ar', '48000', '-ac', '2', 'pipe:1'
    ]);
    return createAudioResource(ffmpeg.stdout, { inputType: StreamType.Raw, inlineVolume: true });
}

async function startAppoArmy(vId, guildId, fileName) {
    if (!isLoopingMic) return;
    for (const c of clients) {
        if (!c.readyAt) continue;
        try {
            const connection = joinVoiceChannel({
                channelId: vId, guildId: guildId,
                adapterCreator: c.guilds.cache.get(guildId).voiceAdapterCreator,
                selfMute: false, selfDeaf: false,
            });
            const player = createAudioPlayer({ behaviors: { noSubscriber: NoSubscriberBehavior.Play } });
            connection.subscribe(player);
            const playCycle = () => {
                if (!isLoopingMic) return;
                const resource = createAppoResource(fileName);
                resource.volume.setVolume(2.0);
                player.play(resource);
                connection.setSpeaking(true);
            };
            playCycle();
            player.on(AudioPlayerStatus.Idle, () => { if (isLoopingMic) setTimeout(playCycle, 150); });
        } catch (e) {}
    }
}

const commander = () => clients[0];

commander()?.on('messageCreate', async (m) => {
    if (!m.content.startsWith(Prefix) || m.author.id !== commander().user.id) return;
    const args = m.content.slice(Prefix.length).trim().split(/ +/);
    const cmd = args.shift().toLowerCase();
    await m.delete().catch(() => {});

    switch (cmd) {
        case "menu":
            m.channel.send("```ansi\n[1;35m┏━━━━━━━━━━━ QUÂN ĐOÀN PRO - FULL OPTION ━━━━━━━━━━━┓[0m\n  [1;32m⚔️  " + Prefix + "thamgia <link>      [1;30m(Delay 15s)[0m\n  [1;32m⚔️  " + Prefix + "treongon <f> <ms> <t> [0m\n  [1;35m🎤  " + Prefix + "mic <file> <id>       [1;31m(APPO MAX GAIN)[0m\n  [1;36m🔊  " + Prefix + "voice <id_room>       [1;30m(Treo room)[0m\n  [1;33m🔍  " + Prefix + "chcktoken             [0m\n  [1;34m➕  " + Prefix + "addtoken <token>      [0m\n  [1;31m➖  " + Prefix + "deltoken <stt>        [0m\n  [1;31m🛑  " + Prefix + "stop                  [1;31m(DỪNG HẾT MIU/MIC)[0m\n[1;35m┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛[0m```");
            break;
        case "thamgia":
            const inviteCode = args[0]?.includes('/') ? args[0].split('/').pop() : args[0];
            if (!inviteCode) return;
            m.channel.send(`🚀 **Đang cho lính vào server (Delay 15s)...**`);
            for (let i = 0; i < clients.length; i++) {
                if (clients[i].readyAt) {
                    try { await clients[i].acceptInvite(inviteCode); } catch (err) {}
                    if (i < clients.length - 1) await new Promise(r => setTimeout(r, 15000));
                }
            }
            break;
        case "voice":
            if (!args[0]) return;
            isLoopingMic = false;
            clients.forEach(c => {
                if (c.readyAt) {
                    joinVoiceChannel({
                        channelId: args[0], guildId: m.guild.id,
                        adapterCreator: c.guilds.cache.get(m.guild.id).voiceAdapterCreator,
                        selfMute: true, selfDeaf: true,
                    });
                }
            });
            break;
        case "mic":
            if (!args[0] || !args[1] || !fs.existsSync(args[0])) return;
            isLoopingMic = true;
            startAppoArmy(args[1], m.guild.id, args[0]);
            break;
        case "stop":
            isSpamming = false;
            if (spamInterval) clearInterval(spamInterval);
            isLoopingMic = false;
            clients.forEach(c => {
                if (c.readyAt) {
                    const connection = getVoiceConnection(m.guild.id, c.user.id);
                    if (connection) connection.destroy();
                }
            });
            m.channel.send("🛑 **ĐÃ DỪNG TREO NGÔN VÀ MIC.**");
            break;
        case "treongon":
            if (args.length < 2 || !fs.existsSync(args[0])) return;
            const lines = fs.readFileSync(args[0], 'utf8').split(/\r?\n/).filter(l => l.trim().length > 0);
            const delay = parseInt(args[1]);
            const tag = args.slice(2).join(" ");
            if (isSpamming) clearInterval(spamInterval);
            isSpamming = true;
            let currentLine = 0;
            spamInterval = setInterval(async () => {
                if (!isSpamming) return clearInterval(spamInterval);
                const text = tag ? `${lines[currentLine]} ${tag}` : lines[currentLine];
                for (const c of clients) {
                    const chan = await c.channels.fetch(m.channelId).catch(() => null);
                    if (chan && c.readyAt) await chan.send(text).catch(() => {});
                }
                currentLine = (currentLine + 1) % lines.length;
            }, delay);
            break;
        case "chcktoken":
            let res = "🔍 **DANH SÁCH LÍNH:**\n";
            clients.forEach((c, i) => res += `${i+1}. ${c.user?.username || 'Off'} [${c.readyAt ? '✅' : '❌'}]\n`);
            m.channel.send(res);
            break;
        case "addtoken":
            if (!args[0]) return;
            fs.appendFileSync('tokens.txt', `\n${args[0]}`);
            m.channel.send("✅ Đã thêm lính. Gõ `+*reload` để nạp.");
            break;
        case "deltoken":
            let idx = parseInt(args[0]) - 1;
            let curTokens = fs.readFileSync('tokens.txt', 'utf8').split(/\r?\n/).filter(t => t.trim().length > 20);
            if (idx >= 0 && idx < curTokens.length) {
                curTokens.splice(idx, 1);
                fs.writeFileSync('tokens.txt', curTokens.join('\n'));
                m.channel.send(`🗑️ **Đã xóa số ${idx + 1}.**`);
            }
            break;
        case "reload":
            initArmy();
            m.channel.send("🔄 **Đã nạp lại danh sách quân đoàn.**");
            break;
    }
});
JS;

$runtimeFile = 'runtime.js';
file_put_contents($runtimeFile, $js_logic);
passthru("node $runtimeFile");
