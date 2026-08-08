// Please see this file for the latest firebase-js-sdk version:
// https://github.com/firebase/flutterfire/blob/master/packages/firebase_core/firebase_core_web/lib/src/firebase_sdk_version.dart
importScripts("https://www.gstatic.com/firebasejs/10.7.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.7.0/firebase-messaging-compat.js");

// const firebaseConfig = {
//     apiKey: "AIzaSyCbAVtyl_NNaQXRdkZgKnPPNXoqMdA_Jtw",
//     authDomain: "ludoshree-aa72a.firebaseapp.com",
//     projectId: "ludoshree-aa72a",
//     storageBucket: "ludoshree-aa72a.appspot.com",
//     messagingSenderId: "778096882740",
//     appId: "1:778096882740:web:f3e3724dbf2a626776c661",
//     measurementId: "G-CVHED3PZZY"
// };

// firebase.initializeApp(firebaseConfig);

firebase.initializeApp({
    apiKey: "AIzaSyCNG3sgTeJdGbL6rPiDLyOJ54M_Z-_AIwU",
    authDomain: "merifactory.firebaseapp.com",
    projectId: "merifactory",
    storageBucket: "merifactory.firebasestorage.app",
    messagingSenderId: "455735529068",
    appId: "1:455735529068:web:6348e4df4064d43de01249",
  });



const messaging = firebase.messaging();

// Handle background messages and show a browser notification
messaging.onBackgroundMessage(function (payload) {
    console.log('[firebase-messaging-sw.js] Received background message ', payload);

    const notificationTitle =
        (payload.notification && payload.notification.title) || 'MeriFactory';

    const notificationOptions = {
        body: payload.notification && payload.notification.body,
        // icon: '/icons/Icon-192.png',
        data: payload.data || {},
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});

// messaging.onBackgroundMessage(function (payload) {
//     console.log('[firebase-messaging-sw.js] Received background message ', payload);
//     const notificationTitle = payload.notification.title;
//     const notificationOptions = {
//         body: payload.notification.body,
//         // icon: '/firebase-logo.png'
//     };

//     self.registration.showNotification(notificationTitle, notificationOptions);
// });

// Optional: handle clicks on the notification (e.g. focus/open a tab)
self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = event.notification.data && event.notification.data.url;
    if (targetUrl) {
        event.waitUntil(clients.openWindow(targetUrl));
    }
});
