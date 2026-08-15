package ir.khoshhesab.app.ui

import android.net.Uri
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.layout.wrapContentWidth
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.Icon
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Error
import androidx.compose.ui.graphics.Color
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import ir.khoshhesab.app.AppViewModel
import kotlinx.coroutines.launch

@Composable
fun KhoshHesabApp(vm: AppViewModel) {
    val nav = rememberNavController()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    var noticeIsError by remember { mutableStateOf(false) }
    vm.notice?.let { notice ->
        LaunchedEffect(notice) {
            noticeIsError = notice.isError
            scope.launch { snackbar.showSnackbar(notice.text) }
            vm.clearNotice()
        }
    }

    CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) {
        Scaffold(snackbarHost = {
            SnackbarHost(snackbar) { data ->
                Surface(
                    modifier = Modifier
                        .wrapContentWidth()
                        .widthIn(max = 340.dp)
                        .padding(horizontal = 16.dp, vertical = 10.dp),
                    shape = RoundedCornerShape(50.dp),
                    color = if (noticeIsError) Color(0xFFD9364E) else Color(0xFF008F68),
                    contentColor = Color.White,
                    shadowElevation = 8.dp
                ) {
                    Row(
                        Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            if (noticeIsError) Icons.Default.Error else Icons.Default.CheckCircle,
                            contentDescription = null,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text(data.visuals.message)
                    }
                }
            }
        }) { _ ->
            NavHost(
                navController = nav,
                startDestination = when {
                    vm.session.token != null -> "home"
                    !vm.session.onboardingSeen -> "onboarding"
                    else -> "welcome"
                }
            ) {
                composable("onboarding") {
                    OnboardingScreen(
                        onFinish = {
                            vm.session.onboardingSeen = true
                            nav.navigate("welcome") {
                                popUpTo("onboarding") { inclusive = true }
                            }
                        }
                    )
                }
                composable("welcome") {
                    WelcomeScreen(
                        onLogin = { nav.navigate("login") },
                        onRegister = { nav.navigate("phone/register") }
                    )
                }
                composable("login") {
                    LoginScreen(
                        vm = vm,
                        onBack = { nav.popBackStack() },
                        onSuccess = {
                            nav.navigate("home") { popUpTo("welcome") { inclusive = true } }
                        },
                        onForgot = { nav.navigate("phone/reset_password") }
                    )
                }
                composable(
                    "phone/{purpose}",
                    arguments = listOf(navArgument("purpose") { type = NavType.StringType })
                ) { entry ->
                    val purpose = entry.arguments?.getString("purpose") ?: "register"
                    PhoneScreen(vm, purpose, { nav.popBackStack() }) { phone ->
                        nav.navigate("otp/${Uri.encode(phone)}/$purpose")
                    }
                }
                composable(
                    "otp/{phone}/{purpose}",
                    arguments = listOf(
                        navArgument("phone") { type = NavType.StringType },
                        navArgument("purpose") { type = NavType.StringType }
                    )
                ) { entry ->
                    val phone = Uri.decode(entry.arguments?.getString("phone").orEmpty())
                    val purpose = entry.arguments?.getString("purpose") ?: "register"
                    OtpScreen(vm, phone, purpose, { nav.popBackStack() }) {
                        val destination = if (purpose == "register")
                            "register/${Uri.encode(phone)}" else "reset/${Uri.encode(phone)}"
                        nav.navigate(destination)
                    }
                }
                composable("register/{phone}") { entry ->
                    RegisterDetailsScreen(
                        vm, Uri.decode(entry.arguments?.getString("phone").orEmpty()),
                        onSuccess = {
                            nav.navigate("home") { popUpTo("welcome") { inclusive = true } }
                        }
                    )
                }
                composable("reset/{phone}") { entry ->
                    ResetPasswordScreen(
                        vm, Uri.decode(entry.arguments?.getString("phone").orEmpty())
                    ) {
                        nav.navigate("login") { popUpTo("welcome") }
                    }
                }
                composable("home") {
                    HomeScreen(
                        vm,
                        openGroup = { nav.navigate("group/$it") },
                        createGroup = { nav.navigate("create-group") },
                        notifications = { nav.navigate("notifications") },
                        chats = { nav.navigate("chats") },
                        profile = { nav.navigate("profile") },
                        logout = {
                            vm.session.clear()
                            nav.navigate("welcome") { popUpTo(0) }
                        }
                    )
                }
                composable("create-group") {
                    CreateGroupScreen(vm, { nav.popBackStack() }) { id ->
                        nav.navigate("group/$id") { popUpTo("home") }
                    }
                }
                composable("group/{id}") { entry ->
                    val id = entry.arguments?.getString("id")?.toLongOrNull() ?: 0
                    GroupScreen(
                        vm, id, { nav.popBackStack() },
                        addExpense = { nav.navigate("expense/$id") },
                        settle = { nav.navigate("settle/$id") },
                        chat = { nav.navigate("chat/$id") }
                    )
                }
                composable("expense/{id}") { entry ->
                    ExpenseFormScreen(
                        vm, entry.arguments?.getString("id")?.toLongOrNull() ?: 0
                    ) { nav.popBackStack() }
                }
                composable("settle/{id}") { entry ->
                    SettlementFormScreen(
                        vm, entry.arguments?.getString("id")?.toLongOrNull() ?: 0
                    ) { nav.popBackStack() }
                }
                composable("chat/{id}") { entry ->
                    ChatScreen(
                        vm, entry.arguments?.getString("id")?.toLongOrNull() ?: 0,
                        onBack = { nav.popBackStack() }
                    )
                }
                composable("notifications") {
                    NotificationsScreen(vm) { nav.popBackStack() }
                }
                composable("chats") {
                    ChatsScreen(vm, { nav.popBackStack() }) { nav.navigate("chat/$it") }
                }
                composable("profile") {
                    ProfileScreen(vm, { nav.popBackStack() }) {
                        nav.navigate("change-phone")
                    }
                }
                composable("change-phone") {
                    ChangePhoneScreen(vm) { nav.popBackStack() }
                }
            }

            if (vm.loading) {
                Box(
                    Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) { CircularProgressIndicator() }
            }
        }
    }
}
