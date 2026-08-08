'use strict';
const MANIFEST = 'flutter-app-manifest';
const TEMP = 'flutter-temp-cache';
const CACHE_NAME = 'flutter-app-cache';

const RESOURCES = {"flutter_bootstrap.js": "8f7c7a8b123f08afbf0ba1aa01e3f13a",
"version.json": "bcf25250960e896aac7ac292462c151d",
"favicon.ico": "6cf56aad169f9b455e25e92355c746fd",
"index.html": "7b83747a60eefb68747254ff6867772b",
"/": "7b83747a60eefb68747254ff6867772b",
"firebase-messaging-sw.js": "d619c8fb69cbe4fa302691182e9adaa1",
"main.dart.js": "cef07c99a3a39e3fd913acb87e1472f3",
"flutter.js": "24bc71911b75b5f8135c949e27a2984e",
"favicon.png": "c65f0f8a250670314618faceb510d02e",
"icons/favicon.ico": "6cf56aad169f9b455e25e92355c746fd",
"icons/apple-touch-icon.png": "cb3f311439a888ed1b1b79368cb191e4",
"icons/icon-192.png": "3c7cf9aab7f06f97fe8913cdaafd0a47",
"icons/Icon-maskable-192.png": "c457ef57daa1d16f64b27b786ec2ea3c",
"icons/icon-192-maskable.png": "8fd8fdca2cf51031b1510f55eb66f7d9",
"icons/icon-512-maskable.png": "ad57bb6bf9dbb96a38fc6cbe3b0479db",
"icons/README.txt": "d3df3991a31f034bfa98afdfa3c622e1",
"icons/Icon-maskable-512.png": "301a7604d45b3e739efc881eb04896ea",
"icons/icon-512.png": "0025fdf4a83e78bda52a675180b1e4c4",
"manifest.json": "c56aa6e802301647863478e510476b61",
"assets/NOTICES": "5043d45e1398e76858fe110d048c91bd",
"assets/FontManifest.json": "c7696364096e83b6ab42e7820ed69056",
"assets/AssetManifest.bin.json": "68422f9b0cb7f7fa18d02e33553ae4ba",
"assets/packages/cupertino_icons/assets/CupertinoIcons.ttf": "d7d83bd9ee909f8a9b348f56ca7b68c6",
"assets/packages/fluttertoast/assets/toastify.js": "56e2c9cedd97f10e7e5f1cebd85d53e3",
"assets/packages/fluttertoast/assets/toastify.css": "a85675050054f179444bc5ad70ffc635",
"assets/packages/wakelock_plus/assets/no_sleep.js": "7748a45cd593f33280669b29c2c8919a",
"assets/shaders/ink_sparkle.frag": "ecc85a2e95f5e9f53123dcaf8cb9b6ce",
"assets/shaders/stretch_effect.frag": "40d68efbbf360632f614c731219e95f0",
"assets/AssetManifest.bin": "5c1d0c21c1b18b84184fd3920145eaf0",
"assets/fonts/MaterialIcons-Regular.otf": "684d2cd6e2eaf74651ce7b75a8dd35bb",
"assets/assets/images/won.png": "ccdacb0098c74438d8e18a9a4fe70324",
"assets/assets/images/warning.png": "4153f461d7e09b912f55ee002379d0c2",
"assets/assets/images/login_design.png": "4dffa122d2a7f517122821caa98e722a",
"assets/assets/images/support.png": "4fc259831de589b5428f63041fc6c898",
"assets/assets/images/banner_4.png": "96e8f94c3cdce5063099de7057503ac9",
"assets/assets/images/circularProfile.png": "37174e2add7add675dd94cb2fcc62f6b",
"assets/assets/images/banner_5.png": "3abfc25525ba2d665becde46a06c16bd",
"assets/assets/images/rules.png": "6fcd90239d13b5efb67d8a651fe15558",
"assets/assets/images/banner_1.png": "6a49f551d4533b655f5f3581fa3edb7c",
"assets/assets/images/banner_2.png": "c94fb8643ad7d215108dba39900f9571",
"assets/assets/images/fire.png": "b88f62584066087d55b38d9d233e1bf2",
"assets/assets/images/banner_3.png": "9cb874f601c3dd4dfe0f3249f80b34ea",
"assets/assets/images/ludo.png": "e9ac405f6e13cb6b2e28317626842ce7",
"assets/assets/images/support_icon.png": "4566971b1d72fb6eda36c21783aafc45",
"assets/assets/images/coinsBG.png": "dfc60cbd86ba194c84d55765b093e439",
"assets/assets/images/otp_design.png": "739286fe15358481bfa6ae22062e009b",
"assets/assets/images/big_banner_1.png": "32194c21c5f0d75382a8e2450917774f",
"assets/assets/images/back_arrow.png": "d0c2e887357e06ba2b726d1698be2739",
"assets/assets/images/big_banner_3.png": "e6f11188ba1a3e9d0121d2dfe98b6d89",
"assets/assets/images/share_icon.png": "986f9971730b158c66a86b01e5f3b77b",
"assets/assets/images/terms_condition.png": "2c7f7d68a4fd2808908a745d3b716dd6",
"assets/assets/images/bank_logo.png": "0a2cd64c518c4d7be6e40452651910d6",
"assets/assets/images/logout.png": "a9ec31845515466af090cd36383058f3",
"assets/assets/images/person_profile.png": "8f30a2c689f725ffe98a7f77268a1a3a",
"assets/assets/images/lost.png": "f2ed9aef55f01ea811fb984b917c9ca6",
"assets/assets/images/big_banner_2.png": "655b32fbe7e6c983239dcd6189ae191c",
"assets/assets/images/emailLogo.png": "c3b1570028ad1a8b56cf404cf5621119",
"assets/assets/images/login_gray_bg.png": "d09b5692497db9bb8a62814d399e2bf4",
"assets/assets/images/coin.gif": "a331ad7d5c38b357f08337b212770296",
"assets/assets/images/leaderboard_img.png": "bf699e1481ea198a1368af419906f799",
"assets/assets/images/winnerCrown.png": "55996a330c3adfcdb4aa822b2b3a0c7e",
"assets/assets/images/big_banner_5.png": "bd05d6f3224f8054c40fb24dd5a648e0",
"assets/assets/images/create.png": "a01397f65aa7ae32dc71828df2da79ac",
"assets/assets/images/upi.png": "c04ab0b52534f5938371e4df64ffacf1",
"assets/assets/images/big_banner_4.png": "59b6b933dcddcfa82d7967461fed91dc",
"assets/assets/images/refer_banner.png": "ce65b417c3e3ccfdce148582bbdc6cd5",
"assets/assets/images/splash_text.png": "5828291073fbbe557d2e42418c4b1fa7",
"assets/assets/images/verses.png": "5806d80111d2af49fc45dfe65be4a469",
"assets/assets/images/score.png": "5612953f8908c5147676fe5ed696e6d9",
"assets/assets/images/help.png": "e19200c6663035212fb7f959f7119b67",
"assets/assets/images/wallet_coin_icon.png": "3c2f143b1b4d3fb896f7576034159c24",
"assets/assets/images/refer.png": "b1cbfdbb783265f704cf45ee621145f5",
"assets/assets/images/share_img.png": "dd1b8852eae0acf9c633560f9ff7a137",
"assets/assets/images/wallet_img.png": "bbdbbefac7fc0063755421fcb5e3e655",
"assets/assets/images/versus.gif": "5849199867a86b0798b1877ab945a6d0",
"assets/assets/images/bank.png": "fd01b134f2e00ee835e16fd3afb4d103",
"assets/assets/images/my_profile_banner.png": "24b9b6b85588455f3e5e3a94f41e1b3b",
"assets/assets/images/refer_bg.png": "cfb78bad2da9c9d57f03502f6e9bb085",
"assets/assets/images/thirdPriceCrown.png": "969d89c23f9652eefd3a3b9e152d54ad",
"assets/assets/images/win_to_wallet.png": "66f2600ab7ba3b6198c74043a82d9fa9",
"assets/assets/images/splash_logo.png": "5f79d70fd2bd70ec16bcb55d13f75021",
"assets/assets/images/create.gif": "118de4515d76b452b20db582d00f096f",
"assets/assets/images/whatsappLogo.png": "a0804b8ea33d355f25f6e8dca4e8266b",
"assets/assets/images/splash_bg.png": "420a44e82ca122c636517ba000611df6",
"assets/assets/images/secondPriceCrown.png": "836222ec312c998df80b73488f3998a2",
"assets/assets/images/telegramLogo.png": "16366de352cd78bc097d4af5495c50d1",
"assets/assets/images/close.png": "2387a16f58aeadc90f72dc5ea2ceff16",
"assets/assets/images/no_data_available.png": "dd1723aa4856067f0a3b69334339be04",
"assets/assets/fonts/SeymourOne-Regular.ttf": "174b8f577840cf3e60c020bc0a084bbd",
"assets/assets/fonts/Moul-Regular.ttf": "904c0b4523c3b82d85fde9f62f1a556c",
"assets/assets/fonts/Montserrat-Bold.ttf": "ed86af2ed5bbaf879e9f2ec2e2eac929",
"assets/assets/fonts/Montserrat-ExtraBold.ttf": "9e07cac927a9b4d955e2138bf6136d6a",
"splash_logo.png": "5f79d70fd2bd70ec16bcb55d13f75021",
"canvaskit/skwasm.js": "8060d46e9a4901ca9991edd3a26be4f0",
"canvaskit/skwasm_heavy.js": "740d43a6b8240ef9e23eed8c48840da4",
"canvaskit/skwasm.js.symbols": "3a4aadf4e8141f284bd524976b1d6bdc",
"canvaskit/canvaskit.js.symbols": "a3c9f77715b642d0437d9c275caba91e",
"canvaskit/skwasm_heavy.js.symbols": "0755b4fb399918388d71b59ad390b055",
"canvaskit/skwasm.wasm": "7e5f3afdd3b0747a1fd4517cea239898",
"canvaskit/chromium/canvaskit.js.symbols": "e2d09f0e434bc118bf67dae526737d07",
"canvaskit/chromium/canvaskit.js": "a80c765aaa8af8645c9fb1aae53f9abf",
"canvaskit/chromium/canvaskit.wasm": "a726e3f75a84fcdf495a15817c63a35d",
"canvaskit/canvaskit.js": "8331fe38e66b3a898c4f37648aaf7ee2",
"canvaskit/canvaskit.wasm": "9b6a7830bf26959b200594729d73538e",
"canvaskit/skwasm_heavy.wasm": "b0be7910760d205ea4e011458df6ee01"};
// The application shell files that are downloaded before a service worker can
// start.
const CORE = ["main.dart.js",
"index.html",
"flutter_bootstrap.js",
"assets/AssetManifest.bin.json",
"assets/FontManifest.json"];

// During install, the TEMP cache is populated with the application shell files.
self.addEventListener("install", (event) => {
  self.skipWaiting();
  return event.waitUntil(
    caches.open(TEMP).then((cache) => {
      return cache.addAll(
        CORE.map((value) => new Request(value, {'cache': 'reload'})));
    })
  );
});
// During activate, the cache is populated with the temp files downloaded in
// install. If this service worker is upgrading from one with a saved
// MANIFEST, then use this to retain unchanged resource files.
self.addEventListener("activate", function(event) {
  return event.waitUntil(async function() {
    try {
      var contentCache = await caches.open(CACHE_NAME);
      var tempCache = await caches.open(TEMP);
      var manifestCache = await caches.open(MANIFEST);
      var manifest = await manifestCache.match('manifest');
      // When there is no prior manifest, clear the entire cache.
      if (!manifest) {
        await caches.delete(CACHE_NAME);
        contentCache = await caches.open(CACHE_NAME);
        for (var request of await tempCache.keys()) {
          var response = await tempCache.match(request);
          await contentCache.put(request, response);
        }
        await caches.delete(TEMP);
        // Save the manifest to make future upgrades efficient.
        await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
        // Claim client to enable caching on first launch
        self.clients.claim();
        return;
      }
      var oldManifest = await manifest.json();
      var origin = self.location.origin;
      for (var request of await contentCache.keys()) {
        var key = request.url.substring(origin.length + 1);
        if (key == "") {
          key = "/";
        }
        // If a resource from the old manifest is not in the new cache, or if
        // the MD5 sum has changed, delete it. Otherwise the resource is left
        // in the cache and can be reused by the new service worker.
        if (!RESOURCES[key] || RESOURCES[key] != oldManifest[key]) {
          await contentCache.delete(request);
        }
      }
      // Populate the cache with the app shell TEMP files, potentially overwriting
      // cache files preserved above.
      for (var request of await tempCache.keys()) {
        var response = await tempCache.match(request);
        await contentCache.put(request, response);
      }
      await caches.delete(TEMP);
      // Save the manifest to make future upgrades efficient.
      await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
      // Claim client to enable caching on first launch
      self.clients.claim();
      return;
    } catch (err) {
      // On an unhandled exception the state of the cache cannot be guaranteed.
      console.error('Failed to upgrade service worker: ' + err);
      await caches.delete(CACHE_NAME);
      await caches.delete(TEMP);
      await caches.delete(MANIFEST);
    }
  }());
});
// The fetch handler redirects requests for RESOURCE files to the service
// worker cache.
self.addEventListener("fetch", (event) => {
  if (event.request.method !== 'GET') {
    return;
  }
  var origin = self.location.origin;
  var key = event.request.url.substring(origin.length + 1);
  // Redirect URLs to the index.html
  if (key.indexOf('?v=') != -1) {
    key = key.split('?v=')[0];
  }
  if (event.request.url == origin || event.request.url.startsWith(origin + '/#') || key == '') {
    key = '/';
  }
  // If the URL is not the RESOURCE list then return to signal that the
  // browser should take over.
  if (!RESOURCES[key]) {
    return;
  }
  // If the URL is the index.html, perform an online-first request.
  if (key == '/') {
    return onlineFirst(event);
  }
  event.respondWith(caches.open(CACHE_NAME)
    .then((cache) =>  {
      return cache.match(event.request).then((response) => {
        // Either respond with the cached resource, or perform a fetch and
        // lazily populate the cache only if the resource was successfully fetched.
        return response || fetch(event.request).then((response) => {
          if (response && Boolean(response.ok)) {
            cache.put(event.request, response.clone());
          }
          return response;
        });
      })
    })
  );
});
self.addEventListener('message', (event) => {
  // SkipWaiting can be used to immediately activate a waiting service worker.
  // This will also require a page refresh triggered by the main worker.
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
    return;
  }
  if (event.data === 'downloadOffline') {
    downloadOffline();
    return;
  }
});
// Download offline will check the RESOURCES for all files not in the cache
// and populate them.
async function downloadOffline() {
  var resources = [];
  var contentCache = await caches.open(CACHE_NAME);
  var currentContent = {};
  for (var request of await contentCache.keys()) {
    var key = request.url.substring(origin.length + 1);
    if (key == "") {
      key = "/";
    }
    currentContent[key] = true;
  }
  for (var resourceKey of Object.keys(RESOURCES)) {
    if (!currentContent[resourceKey]) {
      resources.push(resourceKey);
    }
  }
  return contentCache.addAll(resources);
}
// Attempt to download the resource online before falling back to
// the offline cache.
function onlineFirst(event) {
  return event.respondWith(
    fetch(event.request).then((response) => {
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch((error) => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.match(event.request).then((response) => {
          if (response != null) {
            return response;
          }
          throw error;
        });
      });
    })
  );
}
