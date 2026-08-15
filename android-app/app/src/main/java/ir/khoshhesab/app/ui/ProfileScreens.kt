package ir.khoshhesab.app.ui

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import coil.compose.AsyncImage
import androidx.compose.ui.layout.ContentScale
import ir.khoshhesab.app.BuildConfig
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.User
import kotlinx.coroutines.delay
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody

@Composable
fun ProfileScreen(vm: AppViewModel, onBack: () -> Unit, changePhone: () -> Unit) {
    var user by remember { mutableStateOf<User?>(null) }
    var name by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var localAvatar by remember { mutableStateOf<Uri?>(null) }
    val context = LocalContext.current
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let {
            val bytes = context.contentResolver.openInputStream(it)?.use { stream -> stream.readBytes() }
            if (bytes != null) {
                val mime = context.contentResolver.getType(it) ?: "image/jpeg"
                val part = MultipartBody.Part.createFormData(
                    "avatar",
                    "avatar.${if (mime.contains("png")) "png" else "jpg"}",
                    bytes.toRequestBody(mime.toMediaTypeOrNull())
                )
                vm.request({ vm.api.updateAvatar(part) }) { updated ->
                    user = updated
                    localAvatar = it
                    vm.showSuccess("تصویر پروفایل ذخیره شد")
                }
            }
        }
    }
    LaunchedEffect(Unit) {
        vm.request({ vm.api.profile() }) {
            user = it
            name = it.name.orEmpty()
            email = it.email.orEmpty()
        }
    }
    Column(
        Modifier.fillMaxSize().padding(20.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        PageHeader("پروفایل", onBack)
        Box(
            Modifier.size(112.dp).clip(CircleShape)
                .background(MaterialTheme.colorScheme.primaryContainer)
                .clickable { picker.launch("image/*") },
            contentAlignment = Alignment.Center
        ) {
            val remoteAvatar = user?.avatar?.let {
                if (it.startsWith("http")) it
                else BuildConfig.API_BASE_URL.removeSuffix("api/") + it.trimStart('/')
            }
            if (localAvatar != null || remoteAvatar != null) {
                AsyncImage(
                    model = localAvatar ?: remoteAvatar,
                    contentDescription = "تصویر پروفایل",
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else {
                Icon(
                    Icons.Default.AccountCircle, null,
                    modifier = Modifier.fillMaxSize(),
                    tint = MaterialTheme.colorScheme.primary
                )
            }
            Icon(
                Icons.Default.CameraAlt, "انتخاب تصویر",
                modifier = Modifier.align(Alignment.BottomStart).padding(8.dp)
                    .background(MaterialTheme.colorScheme.surface, CircleShape).padding(5.dp),
                tint = MaterialTheme.colorScheme.primary
            )
        }
        Text("برای انتخاب تصویر لمس کنید", style = MaterialTheme.typography.bodySmall)
        AppField(name, { name = it }, "نام و نام خانوادگی")
        AppField(email, { email = it }, "ایمیل اختیاری", KeyboardType.Email)
        OutlinedTextField(
            value = user?.phone.orEmpty(),
            onValueChange = {},
            readOnly = true,
            label = { Text("شماره موبایل") },
            modifier = Modifier.fillMaxWidth()
        )
        PrimaryButton("ذخیره تغییرات", {
            vm.request({
                vm.api.updateProfile(mapOf("name" to name, "email" to email.ifBlank { null }))
            }) {
                user = it
                vm.showMessage("پروفایل ذخیره شد")
            }
        }, name.isNotBlank())
        OutlinedButton(onClick = changePhone, modifier = Modifier.fillMaxWidth()) {
            Text("تغییر شماره موبایل")
        }
    }
}

@Composable
fun ChangePhoneScreen(vm: AppViewModel, onBack: () -> Unit) {
    var phone by remember { mutableStateOf(vm.session.phoneChangePhone()) }
    var otp by remember { mutableStateOf("") }
    var requestId by remember { mutableLongStateOf(vm.session.phoneChangeRequestId()) }
    var remaining by remember { mutableLongStateOf(0) }

    LaunchedEffect(Unit) {
        while (true) {
            remaining = (vm.session.phoneChangeExpiresAt() - System.currentTimeMillis() / 1000)
                .coerceAtLeast(0)
            delay(1000)
        }
    }

    Column(
        Modifier.fillMaxSize().padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        PageHeader("تغییر شماره موبایل", onBack)
        if (requestId == 0L || remaining == 0L) {
            Text("کد تأیید به شماره جدید ارسال می‌شود.")
            Text(
                "توجه: در هر ۲۴ ساعت فقط دو بار می‌توانید شماره موبایل را تغییر دهید.",
                color = MaterialTheme.colorScheme.primary
            )
            AppField(phone, { phone = it.filter(Char::isDigit).take(11) }, "شماره جدید", KeyboardType.Phone)
            PrimaryButton("ارسال کد", {
                vm.request({ vm.api.requestPhoneChange(mapOf("new_phone" to phone)) }) {
                    requestId = it.request_id ?: 0
                    vm.session.savePhoneChange(requestId, phone, it.expires_at ?: 0)
                    vm.showSuccess("کد تأیید با موفقیت ارسال شد")
                }
            }, phone.length == 11)
        } else {
            Text("کد ارسال‌شده به $phone را وارد کنید.")
            AppField(otp, { otp = it.filter(Char::isDigit).take(7) }, "کد ۷ رقمی", KeyboardType.Number)
            Text("زمان باقی‌مانده: ${remaining / 60}:${(remaining % 60).toString().padStart(2, '0')}")
            PrimaryButton("تأیید و تغییر شماره", {
                vm.request({
                    vm.api.verifyPhoneChange(mapOf(
                        "request_id" to requestId.toString(),
                        "otp" to otp
                    ))
                }) {
                    vm.session.clearPhoneChange()
                    vm.showMessage(it.message)
                    onBack()
                }
            }, otp.length == 7)
        }
    }
}
