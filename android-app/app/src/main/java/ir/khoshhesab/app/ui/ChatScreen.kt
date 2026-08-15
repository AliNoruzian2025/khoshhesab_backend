package ir.khoshhesab.app.ui

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items as gridItems
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.BuildConfig
import ir.khoshhesab.app.data.ChatMessage
import ir.khoshhesab.app.data.Group
import ir.khoshhesab.app.data.SendChatMessageRequest
import ir.khoshhesab.app.data.User
import kotlinx.coroutines.delay
import coil.compose.AsyncImage

private val stickerPack = listOf(
    "😀", "😂", "🥰", "😍", "😎", "🤩", "🥳", "🤗",
    "👍", "👏", "🙌", "🤝", "💪", "🙏", "✌️", "👌",
    "❤️", "💙", "💚", "🧡", "🔥", "✨", "🎉", "🎁",
    "☕", "🍕", "🍔", "🧳", "🚗", "🏠", "💳", "💰",
    "✅", "❌", "⏰", "📌", "🚀", "💯", "😅", "🤔"
)

@Composable
fun ChatScreen(vm: AppViewModel, groupId: Long, onBack: () -> Unit) {
    var group by remember { mutableStateOf<Group?>(null) }
    var messages by remember { mutableStateOf<List<ChatMessage>>(emptyList()) }
    var text by remember { mutableStateOf("") }
    var stickersOpen by remember { mutableStateOf(false) }
    var actionMessage by remember { mutableStateOf<ChatMessage?>(null) }
    var replyTo by remember { mutableStateOf<ChatMessage?>(null) }
    var editing by remember { mutableStateOf<ChatMessage?>(null) }
    var deleting by remember { mutableStateOf<ChatMessage?>(null) }
    var groupRefresh by remember { mutableIntStateOf(0) }
    val listState = rememberLazyListState()

    LaunchedEffect(groupId, groupRefresh) {
        vm.request({ vm.api.group(groupId) }) { group = it.group }
    }
    LaunchedEffect(groupId) {
        while (true) {
            vm.request({ vm.api.group(groupId) }, silent = true) { group = it.group }
            vm.request({ vm.api.messages(groupId) }, silent = true) { messages = it }
            delay(4000)
        }
    }
    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) listState.animateScrollToItem(messages.lastIndex)
    }

    Scaffold(
        topBar = {
            Column(
                Modifier.background(
                    Brush.horizontalGradient(listOf(Color(0xFF164FC5), Color(0xFF2873EF)))
                )
            ) {
                ChatHeader(group?.name ?: "گفت‌وگوی گروه", group?.members.orEmpty(), onBack)
            }
        },
        bottomBar = {
            Column(
                Modifier.fillMaxWidth().imePadding()
                    .background(MaterialTheme.colorScheme.surface)
            ) {
                (editing ?: replyTo)?.let { target ->
                    MessageComposerContext(
                        title = if (editing != null) "ویرایش پیام" else "پاسخ به ${target.sender?.name ?: "پیام"}",
                        preview = messagePreview(target),
                        onClose = {
                            editing = null
                            replyTo = null
                            if (text == target.message) text = ""
                        }
                    )
                }
                AnimatedVisibility(stickersOpen) {
                    StickerPanel { sticker ->
                        vm.request({
                            vm.api.sendMessage(
                                groupId,
                                SendChatMessageRequest(sticker = sticker, reply_to_id = replyTo?.id)
                            )
                        }, silent = true) {
                            messages = messages + it
                            replyTo = null
                        }
                    }
                }
                HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
                Row(
                    Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 8.dp),
                    verticalAlignment = Alignment.Bottom
                ) {
                    ReceiptPicker(
                        vm = vm,
                        value = null,
                        compact = true,
                        onUploaded = { path ->
                            vm.request({
                                vm.api.sendMessage(
                                    groupId,
                                    SendChatMessageRequest(receipt_image = path, reply_to_id = replyTo?.id)
                                )
                            }, silent = true) {
                                messages = messages + it
                                replyTo = null
                            }
                        }
                    )
                    IconButton(onClick = { stickersOpen = !stickersOpen }) {
                        Icon(
                            if (stickersOpen) Icons.Default.Keyboard else Icons.Default.EmojiEmotions,
                            "استیکر",
                            tint = if (stickersOpen) MaterialTheme.colorScheme.primary
                            else MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                    OutlinedTextField(
                        value = text,
                        onValueChange = { text = it },
                        placeholder = { Text("پیام بنویسید...") },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(24.dp),
                        maxLines = 4
                    )
                    Spacer(Modifier.width(5.dp))
                    FloatingActionButton(
                        onClick = {
                            val sending = text.trim()
                            if (sending.isNotEmpty()) {
                                val editingMessage = editing
                                if (editingMessage != null) {
                                    vm.request({
                                        vm.api.editMessage(editingMessage.id, mapOf("message" to sending))
                                    }) { updated ->
                                        messages = messages.map { if (it.id == updated.id) updated else it }
                                        text = ""
                                        editing = null
                                    }
                                } else {
                                    vm.request({
                                        vm.api.sendMessage(
                                            groupId,
                                            SendChatMessageRequest(message = sending, reply_to_id = replyTo?.id)
                                        )
                                    }, silent = true) {
                                        text = ""
                                        replyTo = null
                                        messages = messages + it
                                    }
                                }
                            }
                        },
                        modifier = Modifier.size(48.dp),
                        containerColor = MaterialTheme.colorScheme.primary
                    ) { Icon(Icons.Default.Send, "ارسال") }
                }
            }
        }
    ) { padding ->
        Column(
            Modifier.padding(padding).fillMaxSize().background(
                Brush.verticalGradient(listOf(Color(0xFFEAF1FF), Color(0xFFF7F9FD)))
            )
        ) {
            group?.let {
                DeletionRequestBanner(vm, it, onChanged = { groupRefresh++ }, onDeleted = onBack)
            }
            Box(Modifier.weight(1f).fillMaxWidth()) {
            if (messages.isEmpty() && !vm.loading) {
                Column(
                    Modifier.align(Alignment.Center),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(
                        Icons.Default.MarkChatUnread, null,
                        modifier = Modifier.size(72.dp),
                        tint = Color(0xFF9DB5E8)
                    )
                    Spacer(Modifier.height(12.dp))
                    Text("اولین پیام گروه را شما بفرستید", color = Color(0xFF63718A))
                }
            }
            LazyColumn(
                state = listState,
                modifier = Modifier.fillMaxSize().padding(horizontal = 10.dp),
                contentPadding = PaddingValues(vertical = 14.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(messages, key = { it.id }) { message ->
                    if (message.is_system) SystemMessage(message.message.orEmpty())
                    else MessageBubble(
                            message,
                            message.sender_id == vm.session.userId,
                            onClick = { actionMessage = message }
                        )
                }
            }
            }
        }
    }

    actionMessage?.let { message ->
        MessageActionsSheet(
            message = message,
            mine = message.sender_id == vm.session.userId,
            onDismiss = { actionMessage = null },
            onReply = {
                replyTo = message
                editing = null
                actionMessage = null
                stickersOpen = false
            },
            onEdit = {
                editing = message
                replyTo = null
                text = message.message.orEmpty()
                actionMessage = null
                stickersOpen = false
            },
            onDelete = {
                deleting = message
                actionMessage = null
            }
        )
    }
    deleting?.let { message ->
        AlertDialog(
            onDismissRequest = { deleting = null },
            title = { Text("حذف پیام") },
            text = { Text("این پیام برای همه اعضای گفتگو حذف شود؟") },
            confirmButton = {
                Button(
                    onClick = {
                        vm.request({ vm.api.deleteMessage(message.id) }) {
                            messages = messages.map {
                                if (it.id == message.id) it.copy(
                                    message = null, sticker = null, receipt_image = null, deleted_at = "now"
                                ) else it
                            }
                            deleting = null
                            vm.showSuccess(it.message)
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error)
                ) { Text("حذف") }
            },
            dismissButton = { TextButton(onClick = { deleting = null }) { Text("انصراف") } }
        )
    }
}

@Composable
private fun SystemMessage(text: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
        Surface(
            color = Color(0xFFFFE5E5),
            contentColor = Color(0xFF6B2020),
            shape = RoundedCornerShape(14.dp),
            modifier = Modifier.widthIn(max = 330.dp)
        ) {
            Row(Modifier.padding(horizontal = 12.dp, vertical = 8.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Info, null, modifier = Modifier.size(17.dp))
                Spacer(Modifier.width(6.dp))
                Text(text, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun ChatHeader(name: String, members: List<User>, onBack: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 8.dp, vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        IconButton(onClick = onBack) {
            Icon(Icons.Default.ArrowForward, "بازگشت", tint = Color.White)
        }
        Column(Modifier.weight(1f)) {
            Text(name, color = Color.White, fontWeight = FontWeight.Black, fontSize = 19.sp)
            Text("${members.size} عضو", color = Color.White.copy(alpha = .75f), fontSize = 12.sp)
        }
        Icon(Icons.Default.Lock, "گفت‌وگوی خصوصی گروه", tint = Color.White.copy(alpha = .85f))
    }
    LazyRow(
        Modifier.fillMaxWidth().padding(bottom = 10.dp),
        contentPadding = PaddingValues(horizontal = 14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(members, key = { it.id }) { member -> MemberAvatar(member) }
    }
}

@Composable
private fun MemberAvatar(user: User) {
    Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.width(58.dp)) {
        Box(
            Modifier.size(43.dp).clip(CircleShape).background(Color.White.copy(alpha = .2f)),
            contentAlignment = Alignment.Center
        ) {
            if (!user.avatar.isNullOrBlank()) {
                AsyncImage(
                    model = mediaUrl(user.avatar),
                    contentDescription = user.name,
                    modifier = Modifier.fillMaxSize(),
                    contentScale = ContentScale.Crop
                )
            } else Text(
                user.name?.trim()?.firstOrNull()?.toString() ?: "؟",
                color = Color.White, fontWeight = FontWeight.Black, fontSize = 18.sp
            )
        }
        Spacer(Modifier.height(4.dp))
        Text(
            user.name?.substringBefore(' ') ?: "کاربر",
            color = Color.White,
            fontSize = 10.sp,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
    }
}

@Composable
private fun MessageBubble(message: ChatMessage, mine: Boolean, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth(),
        horizontalArrangement = if (mine) Arrangement.Start else Arrangement.End,
        verticalAlignment = Alignment.Bottom
    ) {
        if (!mine) {
            Box(
                Modifier.size(30.dp).clip(CircleShape).background(Color(0xFFCFDCF6)),
                contentAlignment = Alignment.Center
            ) {
                Text(message.sender?.name?.firstOrNull()?.toString() ?: "؟", fontWeight = FontWeight.Bold)
            }
            Spacer(Modifier.width(5.dp))
        }
        Surface(
            shape = RoundedCornerShape(
                topStart = 20.dp,
                topEnd = 20.dp,
                bottomStart = if (mine) 5.dp else 20.dp,
                bottomEnd = if (mine) 20.dp else 5.dp
            ),
            color = if (mine) Color(0xFF2864E8) else Color.White,
            contentColor = if (mine) Color.White else Color(0xFF182033),
            shadowElevation = if (mine) 1.dp else 3.dp,
            modifier = Modifier.widthIn(max = 300.dp).clickable(onClick = onClick)
        ) {
            Column(Modifier.padding(horizontal = 12.dp, vertical = 8.dp)) {
                if (!mine) {
                    Text(
                        message.sender?.name.orEmpty(),
                        color = Color(0xFF2864E8),
                        fontWeight = FontWeight.Bold,
                        fontSize = 12.sp
                    )
                }
                message.reply_to?.let { replied ->
                    Surface(
                        color = if (mine) Color.White.copy(alpha = .16f) else Color(0xFFEAF1FF),
                        shape = RoundedCornerShape(9.dp),
                        modifier = Modifier.fillMaxWidth().padding(bottom = 6.dp)
                    ) {
                        Column(Modifier.padding(horizontal = 8.dp, vertical = 5.dp)) {
                            Text(
                                replied.sender?.name ?: "پیام قبلی",
                                fontSize = 11.sp,
                                fontWeight = FontWeight.Bold
                            )
                            Text(messagePreview(replied), fontSize = 11.sp, maxLines = 1)
                        }
                    }
                }
                when {
                    message.deleted_at != null -> Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Default.Block, null, modifier = Modifier.size(16.dp))
                        Spacer(Modifier.width(5.dp))
                        Text("این پیام حذف شده است", fontSize = 13.sp)
                    }
                    !message.sticker.isNullOrBlank() -> Text(message.sticker, fontSize = 43.sp)
                    !message.receipt_image.isNullOrBlank() -> {
                        Column {
                            AsyncImage(
                                model = mediaUrl(message.receipt_image),
                                contentDescription = "تصویر رسید",
                                modifier = Modifier.width(220.dp).height(150.dp)
                                    .clip(RoundedCornerShape(14.dp)),
                                contentScale = ContentScale.Crop
                            )
                            Spacer(Modifier.height(5.dp))
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Default.ReceiptLong, null, modifier = Modifier.size(16.dp))
                                Spacer(Modifier.width(5.dp))
                                Text("رسید پرداخت", fontSize = 12.sp)
                            }
                        }
                    }
                    else -> Text(message.message.orEmpty(), fontSize = 15.sp, lineHeight = 23.sp)
                }
                message.created_at?.let {
                    Row(Modifier.align(Alignment.End)) {
                        if (message.edited_at != null) Text("ویرایش‌شده • ", fontSize = 10.sp)
                        Text(
                            it.take(16).takeLast(5),
                            color = if (mine) Color.White.copy(alpha = .7f) else Color(0xFF7B8496),
                            fontSize = 10.sp
                        )
                    }
                }
            }
        }
    }
}

private fun messagePreview(message: ChatMessage): String = when {
    message.deleted_at != null -> "پیام حذف شده"
    !message.receipt_image.isNullOrBlank() -> "تصویر رسید"
    !message.sticker.isNullOrBlank() -> message.sticker
    else -> message.message.orEmpty()
}

@Composable
private fun MessageComposerContext(title: String, preview: String, onClose: () -> Unit) {
    Surface(color = MaterialTheme.colorScheme.primaryContainer) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 7.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(Icons.Default.Reply, null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text(title, fontWeight = FontWeight.Bold, fontSize = 12.sp)
                Text(preview, maxLines = 1, overflow = TextOverflow.Ellipsis, fontSize = 12.sp)
            }
            IconButton(onClick = onClose) { Icon(Icons.Default.Close, "بستن") }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun MessageActionsSheet(
    message: ChatMessage,
    mine: Boolean,
    onDismiss: () -> Unit,
    onReply: () -> Unit,
    onEdit: () -> Unit,
    onDelete: () -> Unit
) {
    val editable = mine && message.deleted_at == null &&
        message.receipt_image.isNullOrBlank() && message.sticker.isNullOrBlank() &&
        !message.message.isNullOrBlank()
    ModalBottomSheet(onDismissRequest = onDismiss) {
        Text("عملیات پیام", Modifier.padding(horizontal = 20.dp, vertical = 8.dp), fontWeight = FontWeight.Black)
        ListItem(
            modifier = Modifier.clickable(onClick = onReply),
            leadingContent = { Icon(Icons.Default.Reply, null) },
            headlineContent = { Text("پاسخ دادن") }
        )
        if (editable) ListItem(
            modifier = Modifier.clickable(onClick = onEdit),
            leadingContent = { Icon(Icons.Default.Edit, null) },
            headlineContent = { Text("ویرایش پیام") }
        )
        if (mine && message.deleted_at == null) ListItem(
            modifier = Modifier.clickable(onClick = onDelete),
            leadingContent = { Icon(Icons.Default.Delete, null, tint = MaterialTheme.colorScheme.error) },
            headlineContent = { Text("حذف پیام", color = MaterialTheme.colorScheme.error) }
        )
        Spacer(Modifier.height(20.dp))
    }
}

fun mediaUrl(path: String): String =
    if (path.startsWith("http")) path
    else BuildConfig.API_BASE_URL.removeSuffix("api/") + path.trimStart('/')

@Composable
private fun StickerPanel(onSticker: (String) -> Unit) {
    Column(
        Modifier.fillMaxWidth().background(Color(0xFFF7F9FD)).padding(top = 10.dp)
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Text("استیکرها", fontWeight = FontWeight.Black)
            Text("${stickerPack.size} استیکر", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        LazyVerticalGrid(
            columns = GridCells.Fixed(8),
            modifier = Modifier.fillMaxWidth().height(205.dp),
            contentPadding = PaddingValues(8.dp)
        ) {
            gridItems(stickerPack) { sticker ->
                IconButton(onClick = { onSticker(sticker) }) {
                    Text(sticker, fontSize = 27.sp)
                }
            }
        }
    }
}
