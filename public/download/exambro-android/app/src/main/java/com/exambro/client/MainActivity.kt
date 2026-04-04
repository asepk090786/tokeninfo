package com.exambro.client

import android.annotation.SuppressLint
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.graphics.Bitmap
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.net.http.SslError
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.view.KeyEvent
import android.view.View
import android.view.WindowManager
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.view.WindowInsetsCompat
import androidx.core.view.WindowInsetsControllerCompat
import com.exambro.client.databinding.ActivityMainBinding

/**
 * MainActivity — Layar utama Exambro Android Client.
 *
 * Fitur lock mode (anti-contek):
 *  • FLAG_SECURE         : blokir screenshot & screen record
 *  • FLAG_KEEP_SCREEN_ON : layar selalu menyala selama ujian
 *  • Immersive fullscreen: sembunyikan status bar & navigation bar
 *  • Block tombol Back   : butuh 5 ketukan → dialog PIN keluar
 *  • Block tombol Volume : diam saat mode kunci aktif
 *  • Block klik kanan & copy/paste via JS injection
 *  • Block long-press WebView (tidak ada context menu)
 *  • onPause AUTO-RETURN : ketika pengguna keluar app, halaman di-reload
 */
class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var prefs: SharedPreferences

    private val handler = Handler(Looper.getMainLooper())
    private var backPressCount = 0
    private val resetBackCount = Runnable { backPressCount = 0 }

    /** Waktu terakhir app masuk ke background (onPause) */
    private var pausedAt = 0L

    companion object {
        const val PREFS_NAME     = "exambro_prefs"
        const val KEY_SERVER_URL = "server_url"
        const val KEY_API_KEY    = "api_key"
        const val KEY_EXIT_PIN   = "exit_pin"
        const val KEY_LOCK_MODE  = "lock_mode"

        /**
         * User-Agent mengandung kata "exambro" agar middleware server
         * (ExambroApiKey.php · matchesExambroUserAgent) mengizinkan akses
         * tanpa perlu menyertakan API key secara manual.
         */
        private const val EXAMBRO_UA =
            "Mozilla/5.0 (Linux; Android 12; ExambroCBT) " +
            "AppleWebKit/537.36 (KHTML, like Gecko) " +
            "Chrome/120.0.0.0 Mobile Safari/537.36 " +
            "Exambro/2.0 CBTClient/Android"
    }

    // ─────────────────────────────────────────────────────────────
    // Lifecycle
    // ─────────────────────────────────────────────────────────────

    @SuppressLint("SetJavaScriptEnabled")
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        prefs = getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

        // Jika belum pernah dikonfigurasi → arahkan ke Setup
        if (!isSetupDone()) {
            goToSetup()
            return
        }

        applyLockFlags()

        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        setFullScreen()
        setupWebView()

        binding.btnRetry.setOnClickListener { loadPage() }
        loadPage()
    }

    override fun onResume() {
        super.onResume()
        binding.webView.onResume()
        if (isLockMode()) {
            setFullScreen()
            // Jika app kembali ke foreground setelah > 3 detik → reload halaman
            // (mencegah user membuka app lain saat ujian)
            if (pausedAt > 0 && System.currentTimeMillis() - pausedAt > 3_000) {
                loadPage()
            }
            pausedAt = 0
        }
    }

    override fun onPause() {
        super.onPause()
        binding.webView.onPause()
        if (isLockMode()) {
            pausedAt = System.currentTimeMillis()
        }
    }

    override fun onDestroy() {
        binding.webView.stopLoading()
        binding.webView.destroy()
        super.onDestroy()
    }

    override fun onWindowFocusChanged(hasFocus: Boolean) {
        super.onWindowFocusChanged(hasFocus)
        if (hasFocus && isLockMode()) setFullScreen()
    }

    // ─────────────────────────────────────────────────────────────
    // Lock flags
    // ─────────────────────────────────────────────────────────────

    private fun applyLockFlags() {
        if (isLockMode()) {
            // Blokir screenshot & screen record
            window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
            // Jaga layar tetap menyala
            window.addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)
        }
    }

    private fun setFullScreen() {
        val controller = WindowInsetsControllerCompat(window, window.decorView)
        controller.hide(WindowInsetsCompat.Type.systemBars())
        controller.systemBarsBehavior =
            WindowInsetsControllerCompat.BEHAVIOR_SHOW_TRANSIENT_BARS_BY_SWIPE
    }

    private fun isLockMode() = prefs.getBoolean(KEY_LOCK_MODE, true)

    // ─────────────────────────────────────────────────────────────
    // WebView setup
    // ─────────────────────────────────────────────────────────────

    @SuppressLint("SetJavaScriptEnabled", "ClickableViewAccessibility")
    private fun setupWebView() {
        with(binding.webView.settings) {
            javaScriptEnabled           = true
            domStorageEnabled           = true
            databaseEnabled             = true
            cacheMode                   = WebSettings.LOAD_DEFAULT
            userAgentString             = EXAMBRO_UA
            allowFileAccess             = false
            allowContentAccess          = false
            setSupportMultipleWindows(false)
            javaScriptCanOpenWindowsAutomatically = false
            builtInZoomControls         = false
            displayZoomControls         = false
            mediaPlaybackRequiresUserGesture = false
        }

        // Blokir long-press (tidak ada context menu: Copy, Paste, dll.)
        binding.webView.isLongClickable = false
        binding.webView.setOnLongClickListener { true }
        binding.webView.isHapticFeedbackEnabled = false

        binding.webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView, newProgress: Int) {
                if (newProgress < 100) {
                    binding.progressBar.visibility = View.VISIBLE
                    binding.progressBar.progress = newProgress
                } else {
                    binding.progressBar.visibility = View.GONE
                }
            }
        }

        binding.webView.webViewClient = object : WebViewClient() {
            override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
                hideError()
                binding.progressBar.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView, url: String) {
                binding.progressBar.visibility = View.GONE
                injectAntiCheatJs(view)
            }

            override fun onReceivedError(
                view: WebView,
                request: WebResourceRequest,
                error: WebResourceError
            ) {
                if (request.isForMainFrame) {
                    val desc = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                        error.description?.toString() ?: "Tidak dapat terhubung ke server"
                    } else {
                        "Tidak dapat terhubung ke server"
                    }
                    showError("Gagal memuat halaman ujian\n\n$desc\n\nPastikan perangkat terhubung ke jaringan sekolah.")
                }
            }

            override fun onReceivedSslError(
                view: WebView,
                handler: SslErrorHandler,
                error: SslError
            ) {
                // Izinkan SSL error agar server LAN / self-signed cert tetap bisa diakses
                handler.proceed()
            }

            override fun shouldOverrideUrlLoading(
                view: WebView,
                request: WebResourceRequest
            ): Boolean {
                // Biarkan semua navigasi ditangani WebView (termasuk connect ke server ujian)
                return false
            }
        }
    }

    /**
     * Injeksikan JS untuk mencegah copy, paste, klik kanan, dan seleksi teks.
     * Dipanggil setiap kali halaman selesai dimuat.
     */
    private fun injectAntiCheatJs(view: WebView) {
        val js = """
            (function() {
                if (window.__exambroLocked) return;
                window.__exambroLocked = true;

                var prevent = function(e) { e.preventDefault(); e.stopPropagation(); return false; };

                document.addEventListener('contextmenu',  prevent, true);
                document.addEventListener('selectstart',  prevent, true);
                document.addEventListener('copy',         prevent, true);
                document.addEventListener('cut',          prevent, true);
                document.addEventListener('dragstart',    prevent, true);
                document.addEventListener('drop',         prevent, true);

                document.addEventListener('keydown', function(e) {
                    var ctrl = e.ctrlKey || e.metaKey;
                    if (ctrl && ['a','A','c','C','x','X','p','P','s','S','u','U'].indexOf(e.key) !== -1) {
                        e.preventDefault();
                    }
                    // PrintScreen
                    if (e.key === 'PrintScreen') { e.preventDefault(); }
                }, true);

                var style = document.createElement('style');
                style.textContent =
                    '* { -webkit-user-select: none !important; user-select: none !important; }' +
                    'input, textarea { -webkit-user-select: text !important; user-select: text !important; }';
                document.head.appendChild(style);
            })();
        """.trimIndent()
        view.evaluateJavascript(js, null)
    }

    // ─────────────────────────────────────────────────────────────
    // Page loading
    // ─────────────────────────────────────────────────────────────

    private fun loadPage() {
        hideError()
        val serverUrl = prefs.getString(KEY_SERVER_URL, "") ?: ""
        val apiKey    = prefs.getString(KEY_API_KEY, "") ?: ""

        if (serverUrl.isEmpty()) { goToSetup(); return }

        if (!isNetworkAvailable()) {
            showError("Tidak ada koneksi jaringan.\n\nPastikan perangkat terhubung ke jaringan ujian sekolah, kemudian tekan \"Coba Lagi\".")
            return
        }

        val url = buildString {
            append(serverUrl.trimEnd('/'))
            append("/exambro")
            if (apiKey.isNotEmpty()) {
                append("?key=")
                append(android.net.Uri.encode(apiKey))
            }
        }

        binding.webView.loadUrl(url)
    }

    // ─────────────────────────────────────────────────────────────
    // Error state
    // ─────────────────────────────────────────────────────────────

    private fun showError(message: String) {
        binding.progressBar.visibility = View.GONE
        binding.webView.visibility     = View.INVISIBLE
        binding.tvStatus.text          = message
        binding.errorContainer.visibility = View.VISIBLE
    }

    private fun hideError() {
        binding.errorContainer.visibility = View.GONE
        binding.webView.visibility        = View.VISIBLE
    }

    // ─────────────────────────────────────────────────────────────
    // Key events – lock mode handlers
    // ─────────────────────────────────────────────────────────────

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        val locked = isLockMode()

        return when {
            keyCode == KeyEvent.KEYCODE_BACK && locked -> {
                backPressCount++
                handler.removeCallbacks(resetBackCount)
                handler.postDelayed(resetBackCount, 3_000)

                if (backPressCount >= 5) {
                    backPressCount = 0
                    showExitPinDialog()
                } else {
                    val remaining = 5 - backPressCount
                    Toast.makeText(this, "Tekan $remaining× lagi untuk opsi keluar", Toast.LENGTH_SHORT).show()
                }
                true
            }

            keyCode == KeyEvent.KEYCODE_BACK && !locked -> {
                if (binding.webView.canGoBack()) {
                    binding.webView.goBack()
                    true
                } else {
                    super.onKeyDown(keyCode, event)
                }
            }

            // Blokir tombol volume agar tidak digunakan sebagai tanda contek
            keyCode == KeyEvent.KEYCODE_VOLUME_UP   && locked -> true
            keyCode == KeyEvent.KEYCODE_VOLUME_DOWN && locked -> true

            else -> super.onKeyDown(keyCode, event)
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Exit PIN dialog
    // ─────────────────────────────────────────────────────────────

    private fun showExitPinDialog() {
        val storedPin = prefs.getString(KEY_EXIT_PIN, "") ?: ""

        if (storedPin.isEmpty()) {
            AlertDialog.Builder(this)
                .setTitle("PIN Belum Diatur")
                .setMessage("PIN keluar belum dikonfigurasi. Buka pengaturan?")
                .setPositiveButton("Buka Pengaturan") { _, _ -> goToSetup() }
                .setNegativeButton("Batal", null)
                .show()
            return
        }

        val input = EditText(this).apply {
            inputType = android.text.InputType.TYPE_CLASS_NUMBER or
                        android.text.InputType.TYPE_NUMBER_VARIATION_PASSWORD
            hint    = "PIN keluar"
            gravity = android.view.Gravity.CENTER
            setPadding(64, 40, 64, 40)
        }

        AlertDialog.Builder(this)
            .setTitle("🔒 Mode Ujian Terkunci")
            .setMessage("Masukkan PIN pengawas untuk membuka opsi keluar")
            .setView(input)
            .setPositiveButton("Konfirmasi") { _, _ ->
                if (input.text.toString() == storedPin) {
                    showExitOptionsDialog()
                } else {
                    Toast.makeText(this, "❌ PIN salah!", Toast.LENGTH_SHORT).show()
                }
            }
            .setNegativeButton("Batal", null)
            .show()
    }

    private fun showExitOptionsDialog() {
        AlertDialog.Builder(this)
            .setTitle("Pilih Aksi")
            .setItems(arrayOf(
                "⚙️  Pengaturan Aplikasi",
                "🔄  Muat Ulang Halaman",
                "❌  Keluar Aplikasi"
            )) { _, which ->
                when (which) {
                    0 -> goToSetup()
                    1 -> loadPage()
                    2 -> finishAffinity()
                }
            }
            .show()
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private fun isNetworkAvailable(): Boolean {
        val cm = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val net  = cm.activeNetwork ?: return false
            val caps = cm.getNetworkCapabilities(net) ?: return false
            caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
        } else {
            @Suppress("DEPRECATION")
            cm.activeNetworkInfo?.isConnected == true
        }
    }

    private fun isSetupDone() =
        (prefs.getString(KEY_SERVER_URL, "") ?: "").isNotEmpty()

    private fun goToSetup() {
        startActivity(Intent(this, SetupActivity::class.java))
        finish()
    }
}
