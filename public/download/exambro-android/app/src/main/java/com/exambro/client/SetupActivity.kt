package com.exambro.client

import android.content.Context
import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.exambro.client.databinding.ActivitySetupBinding

/**
 * SetupActivity — Halaman konfigurasi Exambro Client.
 *
 * Pengawas/admin mengisi:
 *  • URL Server (contoh: https://examinfo.belajar2026.net)
 *  • API Key Exambro (exb_xxxx...)
 *  • PIN keluar mode kunci
 *  • Toggle mode kunci (lock mode)
 */
class SetupActivity : AppCompatActivity() {

    private lateinit var binding: ActivitySetupBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivitySetupBinding.inflate(layoutInflater)
        setContentView(binding.root)

        loadSavedValues()

        binding.btnSave.setOnClickListener { saveAndLaunch() }
        binding.btnReset.setOnClickListener { resetDefaults() }
    }

    // ─────────────────────────────────────────────────────────────
    // Load & Save
    // ─────────────────────────────────────────────────────────────

    private fun loadSavedValues() {
        val prefs = prefs()
        binding.etServerUrl.setText(
            prefs.getString(MainActivity.KEY_SERVER_URL, "https://examinfo.belajar2026.net")
        )
        binding.etApiKey.setText(prefs.getString(MainActivity.KEY_API_KEY, ""))
        binding.etExitPin.setText(prefs.getString(MainActivity.KEY_EXIT_PIN, ""))
        binding.switchLockMode.isChecked = prefs.getBoolean(MainActivity.KEY_LOCK_MODE, true)
    }

    private fun saveAndLaunch() {
        val serverUrl = binding.etServerUrl.text.toString().trim()
        val apiKey    = binding.etApiKey.text.toString().trim()
        val exitPin   = binding.etExitPin.text.toString().trim()
        val lockMode  = binding.switchLockMode.isChecked

        // Validasi
        if (serverUrl.isEmpty()) {
            binding.etServerUrl.error = "URL server wajib diisi"
            binding.etServerUrl.requestFocus()
            return
        }
        if (!serverUrl.startsWith("http://") && !serverUrl.startsWith("https://")) {
            binding.etServerUrl.error = "URL harus diawali http:// atau https://"
            binding.etServerUrl.requestFocus()
            return
        }
        if (lockMode && exitPin.isEmpty()) {
            binding.etExitPin.error = "PIN keluar wajib diisi jika mode kunci aktif"
            binding.etExitPin.requestFocus()
            return
        }
        if (exitPin.isNotEmpty() && exitPin.length < 4) {
            binding.etExitPin.error = "PIN minimal 4 karakter"
            binding.etExitPin.requestFocus()
            return
        }

        // Simpan
        prefs().edit()
            .putString(MainActivity.KEY_SERVER_URL, serverUrl)
            .putString(MainActivity.KEY_API_KEY, apiKey)
            .putString(MainActivity.KEY_EXIT_PIN, exitPin)
            .putBoolean(MainActivity.KEY_LOCK_MODE, lockMode)
            .apply()

        Toast.makeText(this, "Pengaturan tersimpan ✓", Toast.LENGTH_SHORT).show()
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }

    private fun resetDefaults() {
        binding.etServerUrl.setText("https://examinfo.belajar2026.net")
        binding.etApiKey.setText("")
        binding.etExitPin.setText("")
        binding.switchLockMode.isChecked = true
        Toast.makeText(this, "Direset ke default", Toast.LENGTH_SHORT).show()
    }

    private fun prefs() = getSharedPreferences(MainActivity.PREFS_NAME, Context.MODE_PRIVATE)
}
