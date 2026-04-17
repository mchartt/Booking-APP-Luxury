package com.smartdesk.backend.repository;

import com.smartdesk.backend.model.Booking;
import org.springframework.data.jpa.repository.JpaRepository;

public interface BookingRepository extends JpaRepository<Booking, Long> {
}
