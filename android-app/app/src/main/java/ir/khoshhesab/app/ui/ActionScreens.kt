package ir.khoshhesab.app.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.background
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.KeyboardArrowDown
import androidx.compose.material.icons.filled.People
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import ir.khoshhesab.app.AppViewModel
import ir.khoshhesab.app.data.Balance
import ir.khoshhesab.app.data.GroupDetails
import ir.khoshhesab.app.data.User
import ir.khoshhesab.app.data.CreateExpenseRequest
import ir.khoshhesab.app.data.CreateSettlementRequest
import ir.khoshhesab.app.data.SplitRequest
import java.time.LocalDate

@Composable
fun ExpenseFormScreen(vm: AppViewModel, groupId: Long, onDone: () -> Unit) {
    var details by remember { mutableStateOf<GroupDetails?>(null) }
    var amount by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var date by remember { mutableStateOf(LocalDate.now().toString()) }
    var receipt by remember { mutableStateOf("") }
    var payerId by remember { mutableLongStateOf(vm.session.userId) }
    var selected by remember { mutableStateOf<Set<Long>>(emptySet()) }

    LaunchedEffect(groupId) {
        vm.request({ vm.api.group(groupId) }) {
            details = it
            selected = it.group.members.map(User::id).toSet()
        }
    }
    val members = details?.group?.members.orEmpty()
    val memberListState = rememberLazyListState()

    Column(
        Modifier.fillMaxSize().padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        PageHeader("ثبت هزینه", onDone)
        AppField(
            amount,
            { amount = it.normalizeNumberDigits().filter(Char::isDigit) },
            "مبلغ هزینه (تومان)",
            KeyboardType.Number,
            visualTransformation = ThousandsSeparatorTransformation
        )
        Text("مبلغ را به تومان وارد کنید.", style = MaterialTheme.typography.bodySmall)
        AppField(description, { description = it }, "توضیح هزینه")
        PersianDateField(date, "تاریخ هزینه") { selected ->
            date = selected ?: LocalDate.now().toString()
        }
        ReceiptPicker(vm, receipt.ifBlank { null }, { receipt = it })
        Text("پرداخت‌کننده")
        MemberDropdown(members, payerId) { payerId = it }
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Default.People, null, tint = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.width(8.dp))
            Column(Modifier.weight(1f)) {
                Text("تقسیم مساوی بین چه کسانی؟", style = MaterialTheme.typography.titleMedium)
                Text(
                    "${selected.size} نفر از ${members.size} نفر انتخاب شده‌اند",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant
                )
            }
            TextButton(onClick = {
                selected = if (selected.size == members.size) emptySet() else members.map(User::id).toSet()
            }) { Text(if (selected.size == members.size) "لغو همه" else "انتخاب همه") }
        }
        Box(Modifier.weight(1f).fillMaxWidth()) {
            ElevatedCard(Modifier.fillMaxSize()) {
                LazyColumn(
                    state = memberListState,
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = 48.dp)
                ) {
                    items(members, key = { it.id }) { member ->
                        Row(
                            Modifier.fillMaxWidth().padding(horizontal = 8.dp, vertical = 3.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Checkbox(
                                checked = member.id in selected,
                                onCheckedChange = {
                                    selected = if (it) selected + member.id else selected - member.id
                                }
                            )
                            Column {
                                Text(member.name ?: "بدون نام")
                                Text(
                                    member.phone.orEmpty(),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                        HorizontalDivider(Modifier.padding(horizontal = 12.dp))
                    }
                }
            }
            if (memberListState.canScrollForward) {
                Surface(
                    modifier = Modifier.align(Alignment.BottomCenter).fillMaxWidth(),
                    color = MaterialTheme.colorScheme.primaryContainer,
                    tonalElevation = 3.dp
                ) {
                    Row(
                        Modifier.padding(vertical = 7.dp),
                        horizontalArrangement = Arrangement.Center,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Default.KeyboardArrowDown, null)
                        Text("برای دیدن افراد بیشتر، فهرست را بالا بکشید")
                    }
                }
            }
        }
        PrimaryButton("ثبت هزینه", {
            val total = amount.toLongOrNull() ?: 0
            val ids = selected.toList()
            val base = if (ids.isEmpty()) 0 else total / ids.size
            val remainder = if (ids.isEmpty()) 0 else total % ids.size
            val splits = ids.mapIndexed { index, id ->
                SplitRequest(id, base + if (index < remainder) 1 else 0)
            }
            vm.request({
                vm.api.createExpense(groupId, CreateExpenseRequest(
                    amount = total,
                    description = description,
                    expense_date = date,
                    payer_id = payerId,
                    receipt_image = receipt.ifBlank { null },
                    splits = splits
                ))
            }) {
                vm.showSuccess("هزینه با موفقیت ثبت شد")
                onDone()
            }
        }, amount.toLongOrNull()?.let { it > 0 } == true && description.isNotBlank() && selected.isNotEmpty())
    }
}

@Composable
fun SettlementFormScreen(vm: AppViewModel, groupId: Long, onDone: () -> Unit) {
    var details by remember { mutableStateOf<GroupDetails?>(null) }
    var creditorId by remember { mutableLongStateOf(0) }
    var amount by remember { mutableStateOf("") }
    var receipt by remember { mutableStateOf("") }
    LaunchedEffect(groupId) {
        vm.request({ vm.api.group(groupId) }) { details = it }
    }
    val creditors = details?.balances.orEmpty().filter { it.balance > 0 }
    Column(
        Modifier.fillMaxSize().padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp)
    ) {
        PageHeader("درخواست تسویه", onDone)
        Text("طلبکار")
        BalanceDropdown(creditors, creditorId) { creditorId = it }
        AppField(
            amount,
            { amount = it.normalizeNumberDigits().filter(Char::isDigit) },
            "مبلغ تسویه (تومان)",
            KeyboardType.Number,
            visualTransformation = ThousandsSeparatorTransformation
        )
        ReceiptPicker(vm, receipt.ifBlank { null }, { receipt = it })
        Text("پس از ارسال، طلبکار باید پرداخت را تأیید کند.")
        PrimaryButton("ارسال درخواست", {
            vm.request({
                vm.api.createSettlement(groupId, CreateSettlementRequest(
                    debtor_id = vm.session.userId,
                    creditor_id = creditorId,
                    amount = amount.toLongOrNull() ?: 0,
                    receipt_image = receipt.ifBlank { null }
                ))
            }) {
                vm.showSuccess("درخواست تسویه ارسال شد")
                onDone()
            }
        }, creditorId > 0 && (amount.toLongOrNull() ?: 0) > 0)
    }
}

@Composable
private fun MemberDropdown(members: List<User>, selected: Long, onSelect: (Long) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
            Text(members.firstOrNull { it.id == selected }?.name ?: "انتخاب کنید")
        }
        DropdownMenu(expanded, { expanded = false }) {
            members.forEach {
                DropdownMenuItem(
                    text = { Text(it.name ?: it.phone.orEmpty()) },
                    onClick = { onSelect(it.id); expanded = false }
                )
            }
        }
    }
}

@Composable
private fun BalanceDropdown(items: List<Balance>, selected: Long, onSelect: (Long) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth()) {
            Text(items.firstOrNull { it.user_id == selected }?.name ?: "انتخاب طلبکار")
        }
        DropdownMenu(expanded, { expanded = false }) {
            items.forEach {
                DropdownMenuItem(
                    text = { Text("${it.name} — ${it.balance.toTomanText()}") },
                    onClick = { onSelect(it.user_id); expanded = false }
                )
            }
        }
    }
}
