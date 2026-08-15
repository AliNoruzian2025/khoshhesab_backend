package ir.khoshhesab.app.ui

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import coil.compose.AsyncImage
import ir.khoshhesab.app.AppViewModel
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import java.io.File

@Composable
fun ReceiptPicker(
    vm: AppViewModel,
    value: String?,
    onUploaded: (String) -> Unit,
    compact: Boolean = false,
    groupImage: Boolean = false
) {
    val context = LocalContext.current
    var chooserOpen by remember { mutableStateOf(false) }
    var previewUri by remember { mutableStateOf<Uri?>(null) }
    var cameraUri by remember { mutableStateOf<Uri?>(null) }

    fun upload(uri: Uri) {
        val bytes = runCatching {
            context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
        }.getOrNull()
        if (bytes == null) {
            vm.showError("خواندن تصویر امکان‌پذیر نبود")
            return
        }
        val mime = context.contentResolver.getType(uri) ?: "image/jpeg"
        val extension = when {
            mime.contains("png") -> "png"
            mime.contains("webp") -> "webp"
            else -> "jpg"
        }
        val part = MultipartBody.Part.createFormData(
            if (groupImage) "image" else "receipt",
            "image_${System.currentTimeMillis()}.$extension",
            bytes.toRequestBody(mime.toMediaTypeOrNull())
        )
        previewUri = uri
        vm.request({ if (groupImage) vm.api.uploadGroupImage(part) else vm.api.uploadReceipt(part) }) {
            vm.showSuccess(if (groupImage) "تصویر با موفقیت انتخاب شد" else "تصویر رسید با موفقیت بارگذاری شد")
            onUploaded(it.path)
            if (compact) previewUri = null
        }
    }

    val gallery = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri ->
        uri?.let(::upload)
    }
    val camera = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success ->
        if (success) cameraUri?.let(::upload)
    }

    if (compact) {
        IconButton(onClick = { chooserOpen = true }) {
            Icon(Icons.Default.AttachFile, "ارسال تصویر رسید")
        }
    } else {
        OutlinedCard(
            modifier = Modifier.fillMaxWidth().clickable { chooserOpen = true },
            shape = RoundedCornerShape(18.dp)
        ) {
            if (previewUri != null || !value.isNullOrBlank()) {
                Box(Modifier.fillMaxWidth().height(170.dp)) {
                    AsyncImage(
                        model = previewUri ?: value?.let(::mediaUrl),
                        contentDescription = if (groupImage) "تصویر دنگ یا حساب" else "تصویر رسید",
                        modifier = Modifier.fillMaxSize().clip(RoundedCornerShape(18.dp)),
                        contentScale = ContentScale.Crop
                    )
                    Surface(
                        modifier = Modifier.align(Alignment.BottomCenter).padding(10.dp),
                        color = MaterialTheme.colorScheme.surface.copy(alpha = .9f),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Row(Modifier.padding(horizontal = 12.dp, vertical = 7.dp)) {
                            Icon(Icons.Default.Edit, null, modifier = Modifier.size(18.dp))
                            Spacer(Modifier.width(6.dp))
                            Text(if (groupImage) "تغییر تصویر" else "تغییر تصویر رسید")
                        }
                    }
                }
            } else {
                Row(
                    Modifier.fillMaxWidth().padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Box(
                        Modifier.size(46.dp).clip(RoundedCornerShape(14.dp))
                            .background(MaterialTheme.colorScheme.secondaryContainer),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            if (groupImage) Icons.Default.AddPhotoAlternate else Icons.Default.ReceiptLong,
                            null, tint = MaterialTheme.colorScheme.secondary
                        )
                    }
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text(
                            if (groupImage) "تصویر دنگ یا حساب" else "افزودن تصویر رسید",
                            fontWeight = androidx.compose.ui.text.font.FontWeight.Bold
                        )
                        Text("عکس بگیرید یا از گالری انتخاب کنید", style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }

    if (chooserOpen) {
        AlertDialog(
            onDismissRequest = { chooserOpen = false },
            title = { Text(if (groupImage) "انتخاب تصویر" else "تصویر رسید") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    ElevatedCard(
                        Modifier.fillMaxWidth().clickable {
                            chooserOpen = false
                            val directory = File(context.cacheDir, "receipts").apply { mkdirs() }
                            val file = File(directory, "receipt_${System.currentTimeMillis()}.jpg")
                            cameraUri = FileProvider.getUriForFile(
                                context, "${context.packageName}.fileprovider", file
                            )
                            camera.launch(cameraUri!!)
                        }
                    ) {
                        ListItem(
                            leadingContent = { Icon(Icons.Default.PhotoCamera, null) },
                            headlineContent = { Text("باز کردن دوربین") },
                            supportingContent = { Text("همین حالا از رسید عکس بگیرید") }
                        )
                    }
                    ElevatedCard(
                        Modifier.fillMaxWidth().clickable {
                            chooserOpen = false
                            gallery.launch("image/*")
                        }
                    ) {
                        ListItem(
                            leadingContent = { Icon(Icons.Default.PhotoLibrary, null) },
                            headlineContent = { Text("انتخاب از گالری") },
                            supportingContent = { Text("یکی از تصاویر گوشی را انتخاب کنید") }
                        )
                    }
                }
            },
            confirmButton = {},
            dismissButton = { TextButton(onClick = { chooserOpen = false }) { Text("انصراف") } }
        )
    }
}
