package ir.khoshhesab.app.data

import android.content.Context

class SessionStore(context: Context) {
    private val prefs = context.getSharedPreferences("khoshhesab_session", Context.MODE_PRIVATE)

    var token: String?
        get() = prefs.getString("token", null)
        set(value) = prefs.edit().putString("token", value).apply()

    var userId: Long
        get() = prefs.getLong("user_id", 0)
        set(value) = prefs.edit().putLong("user_id", value).apply()

    var onboardingSeen: Boolean
        get() = prefs.getBoolean("onboarding_seen", false)
        set(value) = prefs.edit().putBoolean("onboarding_seen", value).apply()

    fun saveOtp(phone: String, purpose: String, expiresAt: Long) {
        val now = System.currentTimeMillis() / 1000
        val safeExpiresAt = expiresAt.coerceIn(now, now + 120)
        prefs.edit()
            .putString("otp_phone", phone)
            .putString("otp_purpose", purpose)
            .putLong("otp_expires_at", safeExpiresAt)
            .apply()
    }

    fun otpPhone(): String? = prefs.getString("otp_phone", null)
    fun otpPurpose(): String? = prefs.getString("otp_purpose", null)
    fun otpExpiresAt(): Long {
        val now = System.currentTimeMillis() / 1000
        return prefs.getLong("otp_expires_at", 0).coerceAtMost(now + 120)
    }

    fun clearOtp() = prefs.edit()
        .remove("otp_phone").remove("otp_purpose").remove("otp_expires_at").apply()

    fun savePhoneChange(requestId: Long, phone: String, expiresAt: Long) {
        val now = System.currentTimeMillis() / 1000
        val safeExpiresAt = expiresAt.coerceIn(now, now + 120)
        prefs.edit()
            .putLong("phone_change_request_id", requestId)
            .putString("phone_change_phone", phone)
            .putLong("phone_change_expires_at", safeExpiresAt)
            .apply()
    }

    fun phoneChangeRequestId(): Long = prefs.getLong("phone_change_request_id", 0)
    fun phoneChangePhone(): String = prefs.getString("phone_change_phone", "").orEmpty()
    fun phoneChangeExpiresAt(): Long {
        val now = System.currentTimeMillis() / 1000
        return prefs.getLong("phone_change_expires_at", 0).coerceAtMost(now + 120)
    }
    fun clearPhoneChange() = prefs.edit()
        .remove("phone_change_request_id")
        .remove("phone_change_phone")
        .remove("phone_change_expires_at")
        .apply()

    fun clear() {
        prefs.edit()
            .remove("token")
            .remove("user_id")
            .remove("otp_phone")
            .remove("otp_purpose")
            .remove("otp_expires_at")
            .remove("phone_change_request_id")
            .remove("phone_change_phone")
            .remove("phone_change_expires_at")
            .apply()
    }
}
