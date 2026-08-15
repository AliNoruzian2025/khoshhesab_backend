package ir.khoshhesab.app.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowLeft
import androidx.compose.material.icons.automirrored.filled.KeyboardArrowRight
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import java.time.LocalDate

data class PersianDate(val year: Int, val month: Int, val day: Int) {
    fun toGregorian(): LocalDate {
        var jy = year
        var gy: Int
        if (jy > 979) {
            gy = 1600
            jy -= 979
        } else {
            gy = 621
        }
        var days = 365 * jy + (jy / 33) * 8 + ((jy % 33) + 3) / 4 + 78 + day
        days += if (month < 7) (month - 1) * 31 else (month - 7) * 30 + 186
        gy += 400 * (days / 146097)
        days %= 146097
        if (days > 36524) {
            gy += 100 * (--days / 36524)
            days %= 36524
            if (days >= 365) days++
        }
        gy += 4 * (days / 1461)
        days %= 1461
        if (days > 365) {
            gy += (days - 1) / 365
            days = (days - 1) % 365
        }
        var gd = days + 1
        val monthDays = intArrayOf(
            0, 31, if (isGregorianLeap(gy)) 29 else 28, 31, 30, 31, 30,
            31, 31, 30, 31, 30, 31
        )
        var gm = 1
        while (gm <= 12 && gd > monthDays[gm]) {
            gd -= monthDays[gm]
            gm++
        }
        return LocalDate.of(gy, gm, gd)
    }

    fun formatted(): String = "${day.toPersianDigits()} ${monthNames[month - 1]} ${year.toPersianDigits()}"

    companion object {
        val monthNames = listOf(
            "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
            "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"
        )

        fun fromGregorian(date: LocalDate): PersianDate {
            var gy = date.year
            val gm = date.monthValue
            val gd = date.dayOfMonth
            val offsets = intArrayOf(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334)
            var jy: Int
            if (gy > 1600) {
                jy = 979
                gy -= 1600
            } else {
                jy = 0
                gy -= 621
            }
            val gy2 = if (gm > 2) gy + 1 else gy
            var days = 365 * gy + (gy2 + 3) / 4 - (gy2 + 99) / 100 +
                (gy2 + 399) / 400 - 80 + gd + offsets[gm - 1]
            jy += 33 * (days / 12053)
            days %= 12053
            jy += 4 * (days / 1461)
            days %= 1461
            if (days > 365) {
                jy += (days - 1) / 365
                days = (days - 1) % 365
            }
            val jm = if (days < 186) 1 + days / 31 else 7 + (days - 186) / 30
            val jd = 1 + if (days < 186) days % 31 else (days - 186) % 30
            return PersianDate(jy, jm, jd)
        }

        fun fromIso(value: String?): PersianDate = runCatching {
            fromGregorian(LocalDate.parse(value?.take(10) ?: error("empty date")))
        }.getOrElse { fromGregorian(LocalDate.now()) }

        private fun isGregorianLeap(year: Int): Boolean =
            year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)
    }
}

fun String.toPersianDateText(): String = runCatching {
    PersianDate.fromGregorian(LocalDate.parse(take(10))).formatted()
}.getOrDefault(this)

fun todayPersianText(): String {
    return persianDateWithWeekday(LocalDate.now().toString())
}

fun persianDateWithWeekday(isoDate: String): String {
    val date = runCatching { LocalDate.parse(isoDate.take(10)) }.getOrElse { LocalDate.now() }
    val weekDay = when (date.dayOfWeek) {
        java.time.DayOfWeek.SATURDAY -> "شنبه"
        java.time.DayOfWeek.SUNDAY -> "یکشنبه"
        java.time.DayOfWeek.MONDAY -> "دوشنبه"
        java.time.DayOfWeek.TUESDAY -> "سه‌شنبه"
        java.time.DayOfWeek.WEDNESDAY -> "چهارشنبه"
        java.time.DayOfWeek.THURSDAY -> "پنجشنبه"
        java.time.DayOfWeek.FRIDAY -> "جمعه"
    }
    return "$weekDay، ${PersianDate.fromGregorian(date).formatted()}"
}

private fun Int.toPersianDigits(): String = toString().map {
    if (it.isDigit()) "۰۱۲۳۴۵۶۷۸۹"[it.digitToInt()] else it
}.joinToString("")

private fun daysInPersianMonth(year: Int, month: Int): Int = when {
    month <= 6 -> 31
    month <= 11 -> 30
    else -> {
        val start = PersianDate(year, 1, 1).toGregorian()
        val next = PersianDate(year + 1, 1, 1).toGregorian()
        if (java.time.temporal.ChronoUnit.DAYS.between(start, next) == 366L) 30 else 29
    }
}

@Composable
fun PersianDateField(
    value: String?,
    label: String,
    optional: Boolean = false,
    minDate: LocalDate? = null,
    maxDate: LocalDate? = null,
    enabled: Boolean = true,
    onValueChange: (String?) -> Unit
) {
    var open by remember { mutableStateOf(false) }
    OutlinedCard(
        modifier = Modifier.fillMaxWidth().clickable(enabled = enabled) { open = true },
        colors = CardDefaults.outlinedCardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Default.CalendarMonth, null,
                tint = if (enabled) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.outline
            )
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                Text(value?.toPersianDateText() ?: "انتخاب تاریخ")
            }
        }
    }
    if (open) {
        PersianDatePickerDialog(
            initial = PersianDate.fromIso(value),
            optional = optional,
            minDate = minDate,
            maxDate = maxDate,
            onDismiss = { open = false },
            onClear = { onValueChange(null); open = false },
            onConfirm = { onValueChange(it.toGregorian().toString()); open = false }
        )
    }
}

@Composable
private fun PersianDatePickerDialog(
    initial: PersianDate,
    optional: Boolean,
    minDate: LocalDate?,
    maxDate: LocalDate?,
    onDismiss: () -> Unit,
    onClear: () -> Unit,
    onConfirm: (PersianDate) -> Unit
) {
    var year by remember { mutableIntStateOf(initial.year) }
    var month by remember { mutableIntStateOf(initial.month) }
    var day by remember { mutableIntStateOf(initial.day) }
    val maxDay = daysInPersianMonth(year, month)
    val safeDay = day.coerceAtMost(maxDay)
    LaunchedEffect(year, month) {
        if (day > maxDay) day = maxDay
    }
    val firstGregorian = PersianDate(year, month, 1).toGregorian()
    val leading = (firstGregorian.dayOfWeek.value + 1) % 7
    val cells = List(leading) { 0 } + (1..maxDay).toList()
    val selectedDate = PersianDate(year, month, safeDay).toGregorian()
    val selectedIsAllowed = (minDate == null || !selectedDate.isBefore(minDate)) &&
        (maxDate == null || !selectedDate.isAfter(maxDate))

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("انتخاب تاریخ شمسی", fontWeight = FontWeight.Bold) },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Row(
                    Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    IconButton(onClick = {
                        month--
                        if (month == 0) { month = 12; year-- }
                    }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowRight, "ماه قبل") }
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(PersianDate.monthNames[month - 1], style = MaterialTheme.typography.titleMedium)
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            IconButton(onClick = { year-- }) { Text("−") }
                            Text(year.toPersianDigits(), fontWeight = FontWeight.Bold)
                            IconButton(onClick = { year++ }) { Text("+") }
                        }
                    }
                    IconButton(onClick = {
                        month++
                        if (month == 13) { month = 1; year++ }
                    }) { Icon(Icons.AutoMirrored.Filled.KeyboardArrowLeft, "ماه بعد") }
                }
                Row(Modifier.fillMaxWidth()) {
                    listOf("ش", "ی", "د", "س", "چ", "پ", "ج").forEach {
                        Text(it, Modifier.weight(1f), color = MaterialTheme.colorScheme.primary)
                    }
                }
                LazyVerticalGrid(
                    columns = GridCells.Fixed(7),
                    modifier = Modifier.fillMaxWidth().height(245.dp)
                ) {
                    items(cells) { value ->
                        val candidate = if (value > 0) PersianDate(year, month, value).toGregorian() else null
                        val allowed = candidate != null &&
                            (minDate == null || !candidate.isBefore(minDate)) &&
                            (maxDate == null || !candidate.isAfter(maxDate))
                        Box(
                            Modifier.aspectRatio(1f).padding(2.dp)
                                .background(
                                    if (value == safeDay) MaterialTheme.colorScheme.primary else Color.Transparent,
                                    CircleShape
                                )
                                .clickable(enabled = allowed) { day = value },
                            contentAlignment = Alignment.Center
                        ) {
                            if (value > 0) Text(
                                value.toPersianDigits(),
                                color = when {
                                    !allowed -> MaterialTheme.colorScheme.outlineVariant
                                    value == safeDay -> MaterialTheme.colorScheme.onPrimary
                                    else -> MaterialTheme.colorScheme.onSurface
                                }
                            )
                        }
                    }
                }
            }
        },
        confirmButton = {
            TextButton(
                onClick = { onConfirm(PersianDate(year, month, safeDay)) },
                enabled = selectedIsAllowed
            ) { Text("تأیید") }
        },
        dismissButton = {
            Row {
                if (optional) TextButton(onClick = onClear) { Text("حذف تاریخ") }
                TextButton(onClick = onDismiss) { Text("انصراف") }
            }
        }
    )
}
