package ir.khoshhesab.app.ui

import org.junit.Assert.assertEquals
import org.junit.Test
import java.time.LocalDate

class PersianDateTest {
    @Test
    fun gregorian_date_converts_to_jalali_and_back() {
        val jalali = PersianDate.fromGregorian(LocalDate.of(2026, 8, 1))

        assertEquals(PersianDate(1405, 5, 10), jalali)
        assertEquals(LocalDate.of(2026, 8, 1), jalali.toGregorian())
    }
}
