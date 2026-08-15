package ir.khoshhesab.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import ir.khoshhesab.app.ui.KhoshHesabApp
import ir.khoshhesab.app.ui.KhoshHesabTheme

class MainActivity : ComponentActivity() {
    private val viewModel by viewModels<AppViewModel>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            KhoshHesabTheme {
                KhoshHesabApp(viewModel)
            }
        }
    }
}
