package ir.khoshhesab.app

import android.app.Application
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.google.gson.Gson
import com.google.gson.JsonObject
import ir.khoshhesab.app.data.ApiClient
import ir.khoshhesab.app.data.ApiService
import kotlinx.coroutines.launch
import retrofit2.Response
import java.net.SocketTimeoutException

data class UiNotice(val text: String, val isError: Boolean)

class AppViewModel(application: Application) : AndroidViewModel(application) {
    val api: ApiService = ApiClient.create(application)
    val session = ApiClient.session

    var loading by mutableStateOf(false)
        private set
    var notice by mutableStateOf<UiNotice?>(null)
        private set

    fun clearNotice() { notice = null }
    fun showSuccess(value: String) { notice = UiNotice(value, false) }
    fun showError(value: String) { notice = UiNotice(value, true) }
    fun showMessage(value: String) = showSuccess(value)

    fun <T> request(
        block: suspend () -> Response<T>,
        silent: Boolean = false,
        onSuccess: (T) -> Unit = {}
    ) {
        viewModelScope.launch {
            if (!silent) loading = true
            try {
                val response = block()
                val body = response.body()
                if (response.isSuccessful && body != null) {
                    onSuccess(body)
                } else {
                    showError(parseError(response) ?: "خطای ${response.code()}")
                }
            } catch (e: Exception) {
                showError(when {
                    e is SocketTimeoutException -> "پاسخ سرور طول کشید. وضعیت کد را بررسی یا دوباره تلاش کنید."
                    e.message?.contains("Failed to connect") == true ->
                        "اتصال به سرور برقرار نشد. آدرس API را بررسی کنید."
                    else -> "خطایی رخ داد. دوباره تلاش کنید."
                })
            } finally {
                if (!silent) loading = false
            }
        }
    }

    private fun <T> parseError(response: Response<T>): String? = runCatching {
        val json = Gson().fromJson(response.errorBody()?.string(), JsonObject::class.java)
        json?.get("message")?.asString
            ?: json?.getAsJsonObject("errors")?.entrySet()?.firstOrNull()?.value
                ?.asJsonArray?.firstOrNull()?.asString
    }.getOrNull()
}
