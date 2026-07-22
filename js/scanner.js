/* ============================================================
   Camera barcode scanner (html5-qrcode)
   Toggled on from the Scan Out console; feeds detected codes
   into the same lookup pipeline as manual/hardware-scanner input.
   ============================================================ */

let qrScanner = null;
let cameraActive = false;

document.addEventListener('DOMContentLoaded', () => {
  const toggleBtn = document.getElementById('camera-toggle-btn');
  if (!toggleBtn) return;
  toggleBtn.addEventListener('click', toggleCamera);
});

async function toggleCamera() {
  const wrap = document.getElementById('camera-wrap');
  const btn = document.getElementById('camera-toggle-btn');

  if (cameraActive) {
    await stopCamera();
    wrap.classList.remove('show');
    btn.textContent = 'Use camera scanner';
    cameraActive = false;
    return;
  }

  if (typeof Html5Qrcode === 'undefined') {
    toast('Camera scanner library failed to load. Check your internet connection.', 'error');
    return;
  }

  wrap.classList.add('show');
  btn.textContent = 'Stop camera';
  cameraActive = true;

  qrScanner = new Html5Qrcode('qr-reader');
  try {
    await qrScanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 150 } },
      (decodedText) => {
        // Avoid firing repeatedly for the same held-up barcode
        document.getElementById('barcode-input').value = decodedText;
        doLookup(decodedText);
      },
      () => { /* per-frame decode errors are expected while aiming; ignore */ }
    );
  } catch (err) {
    toast('Could not access the camera: ' + err, 'error');
    wrap.classList.remove('show');
    btn.textContent = 'Use camera scanner';
    cameraActive = false;
  }
}

async function stopCamera() {
  if (qrScanner) {
    try {
      await qrScanner.stop();
      qrScanner.clear();
    } catch (e) { /* already stopped */ }
    qrScanner = null;
  }
}
