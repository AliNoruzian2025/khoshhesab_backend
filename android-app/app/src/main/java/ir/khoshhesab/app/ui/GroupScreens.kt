package ir.khoshhesab.app.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.clickable
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.layout.ContentScale
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.ui.draw.clip
import coil.compose.AsyncImage
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.*
import java.time.LocalDate

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GroupScreen(
    vm: AppViewModel,
    groupId: Long,
    onBack: () -> Unit,
    addExpense: () -> Unit,
    settle: () -> Unit,
    chat: () -> Unit
) {
    var details by remember { mutableStateOf<GroupDetails?>(null) }
    var expenses by remember { mutableStateOf<List<Expense>>(emptyList()) }
    var settlements by remember { mutableStateOf<List<Settlement>>(emptyList()) }
    var tab by remember { mutableIntStateOf(0) }
    var refresh by remember { mutableIntStateOf(0) }
    var selectedExpense by remember { mutableStateOf<Expense?>(null) }
    var selectedSettlement by remember { mutableStateOf<Settlement?>(null) }
    var rejectSettlement by remember { mutableStateOf<Settlement?>(null) }
    var editingGroup by remember { mutableStateOf<Group?>(null) }
    var deletingGroup by remember { mutableStateOf<Group?>(null) }
    var renewingGroup by remember { mutableStateOf<Group?>(null) }

    LaunchedEffect(groupId, refresh) {
        vm.request({ vm.api.group(groupId) }) { details = it }
        vm.request({ vm.api.expenses(groupId) }) { expenses = it }
        vm.request({ vm.api.settlements(groupId) }) { settlements = it }
    }
    val group = details?.group

    Scaffold(
        topBar = { PageHeader(group?.name ?: "گروه", onBack) },
        bottomBar = {
            if (group != null) {
                NavigationBar {
                    if (group.is_active && group.expense_entry_closed.not()) {
                        NavigationBarItem(
                            selected = false, onClick = addExpense,
                            icon = { Icon(Icons.Default.ReceiptLong, null) }, label = { Text("هزینه") }
                        )
                    }
                    NavigationBarItem(
                        selected = false, onClick = settle,
                        icon = { Icon(Icons.Default.Payments, null) }, label = { Text("تسویه") }
                    )
                    NavigationBarItem(
                        selected = false, onClick = chat,
                        icon = {
                            BadgedBox(badge = {
                                if ((group?.unread_count ?: 0) > 0) Badge {
                                    Text(group?.unread_count.toString())
                                }
                            }) { Icon(Icons.Default.Chat, null) }
                        }, label = { Text("چت") }
                    )
                }
            }
        }
    ) { padding ->
        Column(Modifier.padding(padding).fillMaxSize()) {
            if (group != null) {
                val isDang = group.type == "dang"
                val accent = if (isDang) Color(0xFF1859D9) else Color(0xFFE66716)
                val container = if (isDang) Color(0xFFE2ECFF) else Color(0xFFFFE9D5)
                ElevatedCard(
                    Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 6.dp),
                    colors = CardDefaults.elevatedCardColors(
                        containerColor = container,
                        contentColor = Color(0xFF111827)
                    )
                ) {
                    Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Surface(
                                    modifier = Modifier.size(64.dp),
                                    shape = CircleShape,
                                    color = Color.White.copy(alpha = .75f)
                                ) {
                                    if (!group.avatar.isNullOrBlank()) AsyncImage(
                                        model = mediaUrl(group.avatar),
                                        contentDescription = group.name,
                                        modifier = Modifier.fillMaxSize().clip(CircleShape),
                                        contentScale = ContentScale.Crop
                                    ) else Box(contentAlignment = Alignment.Center) {
                                        Icon(
                                            if (isDang) Icons.Default.Luggage else Icons.Default.AccountBalance,
                                            null, tint = accent, modifier = Modifier.size(34.dp)
                                        )
                                    }
                                }
                                Text(if (isDang) "دنگ کوتاه‌مدت" else "حساب بلندمدت", color = accent, fontWeight = FontWeight.Bold)
                                Text("کد دعوت: ${group.invite_code}", fontWeight = FontWeight.Bold)
                                group.start_date?.let { Text("شروع: ${it.toPersianDateText()}") }
                                group.due_date?.let { Text("سررسید: ${it.toPersianDateText()}") }
                            }
                            if (group.creator_id == vm.session.userId) {
                                Column(horizontalAlignment = Alignment.End) {
                                    FilledTonalButton(onClick = { editingGroup = group }) {
                                        Icon(Icons.Default.Settings, null, modifier = Modifier.size(18.dp))
                                        Spacer(Modifier.width(5.dp))
                                        Text("مدیریت")
                                    }
                                    if (isDang || group.renewal_available) {
                                        TextButton(onClick = {
                                            if (isDang) {
                                                vm.request({ vm.api.toggleDangActive(group.id) }) {
                                                    vm.showSuccess(it.message)
                                                    refresh++
                                                }
                                            } else {
                                                renewingGroup = group
                                            }
                                        }) {
                                            Text(
                                                if (isDang) {
                                                    if (group.is_active) "غیرفعال کردن دنگ" else "فعال کردن دوباره دنگ"
                                                } else "تمدید حساب",
                                                color = accent
                                            )
                                        }
                                    }
                                    if (!isDang && group.renewal_window_open && !group.all_members_settled) {
                                        Text(
                                            "تمدید پس از تسویه همه اعضا فعال می‌شود",
                                            color = MaterialTheme.colorScheme.error,
                                            fontSize = 12.sp
                                        )
                                    }
                                }
                            }
                        }
                        Button(
                            onClick = chat,
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = accent)
                        ) {
                            Icon(Icons.Default.Forum, null)
                            Spacer(Modifier.width(8.dp))
                            Text("ورود به چت ${group.name}")
                            if (group.unread_count > 0) {
                                Spacer(Modifier.width(8.dp))
                                Badge(containerColor = Color.White, contentColor = accent) {
                                    Text(group.unread_count.toString())
                                }
                            }
                        }
                    }
                }
            }
            if (group?.creator_id == vm.session.userId) {
                val pendingRequests = group.join_requests.filter { it.status == "pending" }
                if (pendingRequests.isNotEmpty()) {
                    JoinRequestsPanel(vm, group, pendingRequests) { refresh++ }
                }
            }
            group?.let {
                DeletionRequestBanner(vm, it, onChanged = { refresh++ }, onDeleted = onBack)
            }
            if (group?.settlement_due == true) {
                SettlementDuePanel(details?.balances.orEmpty())
            }
            PrimaryTabRow(selectedTabIndex = tab) {
                listOf("مانده‌ها", "هزینه‌ها", "تسویه‌ها", "اعضا").forEachIndexed { index, text ->
                    Tab(tab == index, { tab = index }, text = { Text(text) })
                }
            }
            when (tab) {
                0 -> BalanceList(details?.balances.orEmpty())
                1 -> ExpenseList(expenses) { selectedExpense = it }
                2 -> SettlementList(
                    vm, settlements,
                    showDetails = { selectedSettlement = it },
                    approve = { item ->
                        vm.request({ vm.api.approveSettlement(item.id) }) {
                            vm.showSuccess(it.message)
                            refresh++
                        }
                    },
                    reject = { rejectSettlement = it }
                )
                3 -> MemberList(group)
            }
        }
    }

    selectedExpense?.let { ExpenseDetailsDialog(it) { selectedExpense = null } }
    selectedSettlement?.let { SettlementDetailsDialog(it) { selectedSettlement = null } }
    rejectSettlement?.let { item ->
        RejectSettlementDialog(
            onDismiss = { rejectSettlement = null },
            onConfirm = { reason ->
                vm.request({ vm.api.rejectSettlement(item.id, mapOf("rejection_reason" to reason)) }) {
                    vm.showSuccess(it.message)
                    rejectSettlement = null
                    refresh++
                }
            }
        )
    }
    editingGroup?.let { current ->
        EditGroupDialog(
            vm = vm,
            group = current,
            onDismiss = { editingGroup = null },
            onDelete = {
                editingGroup = null
                deletingGroup = current
            },
            onSave = { request ->
                vm.request({ vm.api.updateGroup(current.id, request) }) {
                    vm.showSuccess("تغییرات با موفقیت ذخیره شد")
                    editingGroup = null
                    refresh++
                }
            }
        )
    }
    deletingGroup?.let { current ->
        AlertDialog(
            onDismissRequest = { deletingGroup = null },
            icon = { Icon(Icons.Default.DeleteForever, null, tint = MaterialTheme.colorScheme.error) },
            title = { Text("حذف ${if (current.type == "dang") "دنگ" else "حساب"}") },
            text = {
                Text("«${current.name}» و تمام هزینه‌ها، تسویه‌ها و پیام‌های آن برای همیشه حذف می‌شوند. این عملیات قابل بازگشت نیست.")
            },
            confirmButton = {
                Button(
                    onClick = {
                        vm.request({ vm.api.deleteGroup(current.id) }) {
                            vm.showSuccess(it.message)
                            deletingGroup = null
                            if (it.requires_approval) refresh++ else onBack()
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.error)
                ) { Text("حذف برای همیشه") }
            },
            dismissButton = { TextButton(onClick = { deletingGroup = null }) { Text("انصراف") } }
        )
    }
    renewingGroup?.let { current ->
        RenewAccountDialog(
            group = current,
            onDismiss = { renewingGroup = null },
            onConfirm = { dueDate, reminderDate ->
                vm.request({
                    vm.api.renewAccount(current.id, mapOf(
                        "due_date" to dueDate,
                        "reminder_date" to reminderDate
                    ))
                }) {
                    vm.showSuccess("حساب با تاریخ جدید تمدید شد")
                    renewingGroup = null
                    onBack()
                }
            }
        )
    }
}

@Composable
private fun SettlementDuePanel(balances: List<Balance>) {
    ElevatedCard(
        Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 6.dp),
        colors = CardDefaults.elevatedCardColors(containerColor = Color(0xFFFFF0D8))
    ) {
        Column(Modifier.padding(14.dp), verticalArrangement = Arrangement.spacedBy(7.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.EventBusy, null, tint = Color(0xFFE66716))
                Spacer(Modifier.width(8.dp))
                Text("موعد تسویه حساب فرا رسیده", fontWeight = FontWeight.Black, fontSize = 18.sp)
            }
            Text("ثبت هزینه جدید بسته شده است. مانده اعضا را بررسی و تسویه‌ها را یکی‌یکی ثبت کنید.")
            LazyColumn(Modifier.fillMaxWidth().heightIn(max = 180.dp)) {
                items(balances, key = { it.user_id }) { balance ->
                    Row(
                        Modifier.fillMaxWidth().padding(vertical = 4.dp),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(balance.name ?: "کاربر ${balance.user_id}")
                        Text(
                            when (balance.status) {
                                "creditor" -> "طلبکار: ${balance.balance.toTomanText()}"
                                "debtor" -> "بدهکار: ${kotlin.math.abs(balance.balance).toTomanText()}"
                                else -> "تسویه‌شده"
                            },
                            color = if (balance.status == "debtor") Color(0xFFD32F2F) else Color(0xFF07883F),
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun RenewAccountDialog(
    group: Group,
    onDismiss: () -> Unit,
    onConfirm: (String, String?) -> Unit
) {
    val currentDue = group.due_date?.take(10)?.let { value -> runCatching { LocalDate.parse(value) }.getOrNull() }
    var newDue by remember(group.id) { mutableStateOf("") }
    var reminder by remember(group.id) { mutableStateOf("") }
    val parsedDue = newDue.takeIf(String::isNotBlank)?.let { value -> runCatching { LocalDate.parse(value) }.getOrNull() }
    val parsedReminder = reminder.takeIf(String::isNotBlank)?.let { value -> runCatching { LocalDate.parse(value) }.getOrNull() }
    val valid = currentDue != null && parsedDue != null && parsedDue.isAfter(currentDue) &&
        (parsedReminder == null || (!parsedReminder.isBefore(currentDue) && parsedReminder.isBefore(parsedDue)))

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("تمدید حساب") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("سررسید فعلی: ${group.due_date?.toPersianDateText().orEmpty()}")
                PersianDateField(
                    newDue.ifBlank { null },
                    "سررسید دوره جدید",
                    minDate = currentDue?.plusDays(1)
                ) { newDue = it.orEmpty() }
                PersianDateField(
                    reminder.ifBlank { null },
                    "یادآور دوره جدید (اختیاری)",
                    optional = true,
                    minDate = currentDue,
                    maxDate = parsedDue?.minusDays(1),
                    enabled = parsedDue != null
                ) { reminder = it.orEmpty() }
                Text(
                    if (valid) "یادآور اختیاری است."
                    else "سررسید جدید باید بعد از سررسید فعلی و یادآور قبل از سررسید جدید باشد.",
                    color = if (valid) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.error,
                    style = MaterialTheme.typography.bodySmall
                )
            }
        },
        confirmButton = {
            Button(onClick = { onConfirm(newDue, reminder.ifBlank { null }) }, enabled = valid) {
                Text("ثبت تمدید")
            }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("انصراف") } }
    )
}

@Composable
private fun EditGroupDialog(
    vm: AppViewModel,
    group: Group,
    onDismiss: () -> Unit,
    onDelete: () -> Unit,
    onSave: (UpdateGroupRequest) -> Unit
) {
    var name by remember(group.id) { mutableStateOf(group.name) }
    var startDate by remember(group.id) {
        mutableStateOf(group.start_date?.take(10) ?: LocalDate.now().toString())
    }
    var dueDate by remember(group.id) { mutableStateOf(group.due_date?.take(10).orEmpty()) }
    var reminderDate by remember(group.id) { mutableStateOf(group.reminder_date?.take(10).orEmpty()) }
    var avatar by remember(group.id) { mutableStateOf(group.avatar.orEmpty()) }
    val start = runCatching { LocalDate.parse(startDate) }.getOrNull()
    val due = runCatching { LocalDate.parse(dueDate) }.getOrNull()
    val reminder = runCatching { LocalDate.parse(reminderDate) }.getOrNull()
    val minimumDue = listOfNotNull(start, LocalDate.now()).maxOrNull()
    val datesValid = group.type == "dang" || (
        start != null && due != null && minimumDue != null && !due.isBefore(minimumDue) &&
            (reminder == null || (!reminder.isBefore(LocalDate.now()) && reminder.isBefore(due)))
        )

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("مدیریت ${if (group.type == "dang") "دنگ" else "حساب"}") },
        text = {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                item { AppField(name, { name = it }, "نام") }
                item {
                    ReceiptPicker(
                        vm = vm,
                        value = avatar.ifBlank { null },
                        onUploaded = { avatar = it },
                        groupImage = true
                    )
                }
                item {
                    OutlinedCard(Modifier.fillMaxWidth()) {
                        Column(Modifier.padding(14.dp)) {
                            Text("تاریخ شروع (غیرقابل تغییر)", color = MaterialTheme.colorScheme.primary)
                            Text(startDate.toPersianDateText(), fontWeight = FontWeight.Bold)
                        }
                    }
                }
                if (group.type == "account") {
                    item {
                        PersianDateField(dueDate.ifBlank { null }, "تاریخ سررسید", minDate = minimumDue) {
                            dueDate = it.orEmpty()
                            val newDue = it?.let { value -> LocalDate.parse(value) }
                            if (newDue != null && reminder != null && !reminder.isBefore(newDue)) reminderDate = ""
                        }
                    }
                    item {
                        PersianDateField(
                            reminderDate.ifBlank { null },
                            "تاریخ یادآوری (اختیاری)",
                            optional = true,
                            minDate = LocalDate.now(),
                            maxDate = due?.minusDays(1),
                            enabled = due != null
                        ) { reminderDate = it.orEmpty() }
                    }
                    item {
                        Text(
                            when {
                                due == null -> "انتخاب تاریخ سررسید الزامی است."
                                minimumDue != null && due.isBefore(minimumDue) -> "خطا: سررسید نمی‌تواند قبل از تاریخ شروع یا تاریخ امروز باشد."
                                reminder != null && reminder.isBefore(LocalDate.now()) -> "خطا: یادآور نمی‌تواند قبل از تاریخ امروز باشد."
                                reminder != null && !reminder.isBefore(due) -> "خطا: یادآور باید قبل از تاریخ سررسید باشد."
                                else -> "یادآور اختیاری است و در صورت انتخاب باید قبل از سررسید باشد."
                            },
                            style = MaterialTheme.typography.bodySmall,
                            color = if (datesValid) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.error
                        )
                    }
                }
                item {
                    OutlinedButton(
                        onClick = onDelete,
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = MaterialTheme.colorScheme.error)
                    ) {
                        Icon(Icons.Default.DeleteForever, null)
                        Spacer(Modifier.width(6.dp))
                        Text("حذف ${if (group.type == "dang") "دنگ" else "حساب"}")
                    }
                }
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    onSave(UpdateGroupRequest(
                        name = name.trim(),
                        due_date = dueDate.ifBlank { null },
                        reminder_date = reminderDate.ifBlank { null },
                        avatar = avatar.ifBlank { null }
                    ))
                },
                enabled = name.isNotBlank() && start != null && datesValid
            ) { Text("ذخیره تغییرات") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("انصراف") } }
    )
}

@Composable
private fun JoinRequestsPanel(
    vm: AppViewModel,
    group: Group,
    requests: List<JoinRequest>,
    onHandled: () -> Unit
) {
    var expanded by remember { mutableStateOf(true) }
    ElevatedCard(
        Modifier.fillMaxWidth().padding(horizontal = 14.dp, vertical = 6.dp),
        colors = CardDefaults.elevatedCardColors(
            containerColor = Color(0xFFFFF3D6),
            contentColor = Color(0xFF302100)
        )
    ) {
        Column(Modifier.padding(12.dp)) {
            Row(
                Modifier.fillMaxWidth().clickable { expanded = !expanded },
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(Icons.Default.PersonAdd, null, tint = Color(0xFFE66716))
                Spacer(Modifier.width(8.dp))
                Text("درخواست‌های عضویت", Modifier.weight(1f), fontWeight = FontWeight.Black)
                Badge(containerColor = Color(0xFFE66716)) { Text(requests.size.toString()) }
                Icon(if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore, null)
            }
            if (expanded) {
                requests.forEach { request ->
                    HorizontalDivider(Modifier.padding(vertical = 8.dp))
                    Row(
                        Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(Modifier.weight(1f)) {
                            Text(request.user?.name ?: "کاربر ${request.user_id}", fontWeight = FontWeight.Bold)
                            request.user?.phone?.let { Text(it, fontSize = 12.sp) }
                        }
                        FilledTonalButton(onClick = {
                            vm.request({
                                vm.api.handleJoinRequest(group.id, request.id, mapOf("status" to "approved"))
                            }) {
                                vm.showSuccess(it.message)
                                onHandled()
                            }
                        }) { Text("قبول") }
                        Spacer(Modifier.width(6.dp))
                        OutlinedButton(onClick = {
                            vm.request({
                                vm.api.handleJoinRequest(group.id, request.id, mapOf("status" to "rejected"))
                            }) {
                                vm.showSuccess(it.message)
                                onHandled()
                            }
                        }) { Text("رد", color = MaterialTheme.colorScheme.error) }
                    }
                }
            }
        }
    }
}

@Composable
private fun BalanceList(items: List<Balance>) = LazyColumn(
    Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)
) {
    items(items) { balance ->
        ListItem(
            headlineContent = { Text(balance.name ?: "کاربر ${balance.user_id}") },
            supportingContent = {
                Text(when (balance.status) {
                    "creditor" -> "طلبکار"
                    "debtor" -> "بدهکار"
                    else -> "تسویه"
                })
            },
            trailingContent = {
                Text(
                    balance.balance.toTomanText(),
                    color = when {
                        balance.balance > 0 -> Color(0xFF07883F)
                        balance.balance < 0 -> Color(0xFFD32F2F)
                        else -> Color.Gray
                    },
                    fontWeight = FontWeight.Bold
                )
            }
        )
        HorizontalDivider()
    }
}

@Composable
private fun ExpenseList(expenses: List<Expense>, showDetails: (Expense) -> Unit) = LazyColumn(
    Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)
) {
    items(expenses, key = { it.id }) { expense ->
        ElevatedCard(
            Modifier.fillMaxWidth().clickable { showDetails(expense) },
            colors = CardDefaults.elevatedCardColors(
                containerColor = Color(0xFFF1F5FF),
                contentColor = Color(0xFF111827)
            )
        ) {
            ListItem(
                headlineContent = { Text(expense.description, fontWeight = FontWeight.Bold) },
                supportingContent = {
                    Text("${expense.payer?.name ?: "پرداخت‌کننده"} • ${expense.expense_date.toPersianDateText()}")
                },
                trailingContent = {
                    Text(expense.amount.toTomanText(), fontSize = 18.sp, fontWeight = FontWeight.Black)
                }
            )
        }
    }
}

@Composable
private fun SettlementList(
    vm: AppViewModel,
    settlements: List<Settlement>,
    showDetails: (Settlement) -> Unit,
    approve: (Settlement) -> Unit,
    reject: (Settlement) -> Unit
) =
    LazyColumn(Modifier.fillMaxSize().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        items(settlements, key = { it.id }) { item ->
            ElevatedCard(Modifier.fillMaxWidth().clickable { showDetails(item) }) {
                Column(Modifier.padding(14.dp)) {
                    Text("${item.debtor?.name} ← ${item.creditor?.name}", fontWeight = FontWeight.Bold)
                    Text(item.amount.toTomanText(), fontSize = 20.sp, fontWeight = FontWeight.Black)
                    Text(
                        settlementStatusText(item.status),
                        color = settlementStatusColor(item.status),
                        fontWeight = FontWeight.Bold
                    )
                    if (item.status == "pending" && item.creditor_id == vm.session.userId) {
                        Row {
                            TextButton(onClick = { approve(item) }) { Text("تأیید") }
                            TextButton(onClick = { reject(item) }) {
                                Text("رد با ذکر دلیل", color = MaterialTheme.colorScheme.error)
                            }
                        }
                    }
                }
            }
        }
    }

private fun settlementStatusText(status: String) = when (status) {
    "approved" -> "تأیید شد"
    "rejected" -> "رد شد"
    else -> "در انتظار تأیید طلبکار"
}

private fun settlementStatusColor(status: String) = when (status) {
    "approved" -> Color(0xFF07883F)
    "rejected" -> Color(0xFFD32F2F)
    else -> Color(0xFFE66716)
}

@Composable
private fun ExpenseDetailsDialog(expense: Expense, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("جزئیات هزینه") },
        text = {
            LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                item { Text(expense.description, fontWeight = FontWeight.Black, fontSize = 19.sp) }
                item { Text(expense.amount.toTomanText(), fontWeight = FontWeight.Black, fontSize = 24.sp) }
                item { Text("پرداخت‌کننده: ${expense.payer?.name ?: "نامشخص"}") }
                item { Text("تاریخ هزینه: ${expense.expense_date.toPersianDateText()}") }
                if (!expense.receipt_image.isNullOrBlank()) item {
                    AsyncImage(
                        model = mediaUrl(expense.receipt_image),
                        contentDescription = "رسید هزینه",
                        modifier = Modifier.fillMaxWidth().height(180.dp),
                        contentScale = ContentScale.Crop
                    )
                }
                item { Text("سهم اعضا", fontWeight = FontWeight.Bold) }
                items(expense.splits, key = { it.id ?: it.user_id }) { split ->
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(split.user?.name ?: "کاربر ${split.user_id}")
                        Text(split.share_amount.toTomanText(), fontWeight = FontWeight.Bold)
                    }
                }
            }
        },
        confirmButton = { TextButton(onClick = onDismiss) { Text("بستن") } }
    )
}

@Composable
private fun SettlementDetailsDialog(item: Settlement, onDismiss: () -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("جزئیات تسویه") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(item.amount.toTomanText(), fontWeight = FontWeight.Black, fontSize = 24.sp)
                Text("بدهکار: ${item.debtor?.name ?: "نامشخص"}")
                Text("طلبکار: ${item.creditor?.name ?: "نامشخص"}")
                Text(
                    "وضعیت: ${settlementStatusText(item.status)}",
                    color = settlementStatusColor(item.status),
                    fontWeight = FontWeight.Bold
                )
                item.rejection_reason?.let {
                    Surface(color = MaterialTheme.colorScheme.errorContainer) {
                        Text("دلیل رد: $it", Modifier.padding(12.dp))
                    }
                }
                if (!item.receipt_image.isNullOrBlank()) AsyncImage(
                    model = mediaUrl(item.receipt_image),
                    contentDescription = "رسید تسویه",
                    modifier = Modifier.fillMaxWidth().height(180.dp),
                    contentScale = ContentScale.Crop
                )
            }
        },
        confirmButton = { TextButton(onClick = onDismiss) { Text("بستن") } }
    )
}

@Composable
private fun RejectSettlementDialog(onDismiss: () -> Unit, onConfirm: (String) -> Unit) {
    var reason by remember { mutableStateOf("") }
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("دلیل رد تسویه") },
        text = {
            AppField(
                value = reason,
                onValueChange = { reason = it.take(255) },
                label = "دلیل رد را بنویسید",
                singleLine = false
            )
        },
        confirmButton = {
            Button(onClick = { onConfirm(reason.trim()) }, enabled = reason.isNotBlank()) { Text("ثبت رد") }
        },
        dismissButton = { TextButton(onClick = onDismiss) { Text("انصراف") } }
    )
}

@Composable
private fun MemberList(group: Group?) =
    LazyColumn(Modifier.fillMaxSize()) {
    items(group?.members.orEmpty(), key = { it.id }) {
        ListItem(
            leadingContent = { Icon(Icons.Default.AccountCircle, null) },
            headlineContent = { Text(it.name ?: "بدون نام") },
            supportingContent = { Text(it.phone.orEmpty()) }
        )
    }
}
