package ir.khoshhesab.app.ui

import androidx.compose.foundation.Image

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.background
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalanceWallet
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale
import ir.khoshhesab.app.R
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import ir.khoshhesab.app.AppViewModel
import kotlinx.coroutines.delay

@Composable
fun WelcomeScreen(onLogin: () -> Unit, onRegister: () -> Unit) {
    Column(
        Modifier.fillMaxSize()
            .background(
                Brush.verticalGradient(
                    listOf(Color(0xFFDCE7FF), Color(0xFFF4F7FC), Color.White)
                )
            )
            .padding(28.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Surface(
            modifier = Modifier.size(150.dp),
            shape = RoundedCornerShape(38.dp),
            shadowElevation = 12.dp
        ) {
            Image(
                painter = painterResource(R.drawable.khoshhesab_logo),
                contentDescription = "لوگوی خوش حساب",
                modifier = Modifier.fillMaxSize(),
                contentScale = ContentScale.Crop
            )
        }
        Spacer(Modifier.height(20.dp))
        Text("خوش حساب", fontSize = 34.sp, fontWeight = FontWeight.Bold)
        Text("دنگ‌ها را ساده، شفاف و دوستانه حساب کن")
        Spacer(Modifier.height(48.dp))
        PrimaryButton("ورود", onLogin)
        Spacer(Modifier.height(12.dp))
        OutlinedButton(
            onClick = onRegister,
            modifier = Modifier.fillMaxWidth().height(52.dp)
        ) { Text("ساخت حساب جدید") }
    }
}

@Composable
fun LoginScreen(vm: AppViewModel, onBack: () -> Unit, onSuccess: () -> Unit, onForgot: () -> Unit) {
    var phone by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        PageHeader("ورود به خوش حساب", onBack)
        Spacer(Modifier.height(20.dp))
        AppField(phone, { phone = it }, "شماره موبایل", KeyboardType.Phone)
        AppField(password, { password = it }, "رمز عبور", KeyboardType.Password)
        PrimaryButton("ورود", {
            vm.request({ vm.api.login(mapOf("phone" to phone, "password" to password)) }) {
                vm.session.token = it.token
                vm.session.userId = it.user?.id ?: 0
                onSuccess()
            }
        }, phone.length >= 10 && password.length >= 6)
        TextButton(onClick = onForgot, modifier = Modifier.align(Alignment.CenterHorizontally)) {
            Text("رمز عبورم را فراموش کرده‌ام")
        }
    }
}

@Composable
fun PhoneScreen(vm: AppViewModel, purpose: String, onBack: () -> Unit, onSent: (String) -> Unit) {
    var phone by remember { mutableStateOf("") }
    var savedRemaining by remember { mutableLongStateOf(0) }
    val title = if (purpose == "register") "ثبت‌نام" else "بازیابی رمز عبور"
    LaunchedEffect(Unit) {
        if (vm.session.otpPurpose() == purpose) {
            phone = vm.session.otpPhone().orEmpty()
            savedRemaining = (vm.session.otpExpiresAt() - System.currentTimeMillis() / 1000)
                .coerceAtLeast(0)
        }
    }
    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        PageHeader(title, onBack)
        Text("شماره موبایل خود را وارد کنید تا کد تأیید برایتان ارسال شود.")
        AppField(phone, { phone = it.filter(Char::isDigit).take(11) }, "۰۹xxxxxxxxx", KeyboardType.Phone)
        if (savedRemaining > 0 && phone == vm.session.otpPhone()) {
            OutlinedButton(onClick = { onSent(phone) }, modifier = Modifier.fillMaxWidth()) {
                Text("ادامه وارد کردن کد قبلی")
            }
        }
        PrimaryButton("ارسال کد تأیید", {
            vm.request({
                vm.api.sendOtp(mapOf("mobile" to phone, "purpose" to purpose))
            }) {
                vm.session.saveOtp(phone, purpose, it.expires_at ?: 0)
                vm.showSuccess("کد تأیید با موفقیت ارسال شد")
                onSent(phone)
            }
        }, phone.length == 11)
    }
}

@Composable
fun OtpScreen(
    vm: AppViewModel,
    phone: String,
    purpose: String,
    onBack: () -> Unit,
    onVerified: () -> Unit
) {
    var otp by remember { mutableStateOf("") }
    var remaining by remember { mutableLongStateOf(0) }

    LaunchedEffect(phone, purpose) {
        vm.request({ vm.api.otpStatus(phone, purpose) }) {
            val expiry = it.expires_at ?: vm.session.otpExpiresAt()
            vm.session.saveOtp(phone, purpose, expiry)
            remaining = (expiry - System.currentTimeMillis() / 1000).coerceAtLeast(0)
        }
    }
    LaunchedEffect(Unit) {
        while (true) {
            remaining = (vm.session.otpExpiresAt() - System.currentTimeMillis() / 1000).coerceAtLeast(0)
            delay(1000)
        }
    }

    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        PageHeader("تأیید شماره", onBack)
        Text("کد ارسال‌شده به $phone را وارد کنید.")
        AppField(otp, { otp = it.filter(Char::isDigit).take(7) }, "کد ۷ رقمی", KeyboardType.Number)
        Text(
            if (remaining > 0) "ارسال مجدد تا ${remaining / 60}:${(remaining % 60).toString().padStart(2, '0')}"
            else "زمان کد تمام شده است",
            color = if (remaining > 0) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error
        )
        PrimaryButton("تأیید کد", {
            vm.request({
                vm.api.verifyOtp(mapOf("mobile" to phone, "otp" to otp, "purpose" to purpose))
            }) {
                vm.session.clearOtp()
                onVerified()
            }
        }, otp.length == 7 && remaining > 0)
        if (remaining == 0L) {
            TextButton(onClick = {
            vm.request({ vm.api.sendOtp(mapOf("mobile" to phone, "purpose" to purpose)) }) {
                vm.session.saveOtp(phone, purpose, it.expires_at ?: 0)
                vm.showSuccess("کد تأیید دوباره ارسال شد")
            }
            }) { Text("ارسال دوباره کد") }
        }
    }
}

@Composable
fun RegisterDetailsScreen(vm: AppViewModel, phone: String, onSuccess: () -> Unit) {
    var name by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmation by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        PageHeader("تکمیل ثبت‌نام")
        AppField(name, { name = it }, "نام و نام خانوادگی")
        AppField(password, { password = it }, "رمز عبور انگلیسی", KeyboardType.Password)
        AppField(confirmation, { confirmation = it }, "تکرار رمز عبور", KeyboardType.Password)
        PrimaryButton("ثبت‌نام و ورود", {
            vm.request({
                vm.api.register(mapOf(
                    "phone" to phone, "name" to name, "password" to password,
                    "password_confirmation" to confirmation
                ))
            }) {
                vm.session.token = it.token
                vm.session.userId = it.user?.id ?: 0
                onSuccess()
            }
        }, name.isNotBlank() && password.length >= 6 && password == confirmation)
    }
}

@Composable
fun ResetPasswordScreen(vm: AppViewModel, phone: String, onSuccess: () -> Unit) {
    var password by remember { mutableStateOf("") }
    var confirmation by remember { mutableStateOf("") }
    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        PageHeader("رمز عبور جدید")
        AppField(password, { password = it }, "رمز جدید", KeyboardType.Password)
        AppField(confirmation, { confirmation = it }, "تکرار رمز جدید", KeyboardType.Password)
        PrimaryButton("تغییر رمز عبور", {
            vm.request({
                vm.api.resetPassword(mapOf(
                    "phone" to phone, "password" to password,
                    "password_confirmation" to confirmation
                ))
            }) {
                vm.showMessage(it.message)
                onSuccess()
            }
        }, password.length >= 6 && password == confirmation)
    }
}
