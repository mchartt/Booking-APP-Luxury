package com.smartdesk.backend.dto;

import java.time.LocalDateTime;

public record BookingDTO(Long bookingID, Long deskID, LocalDateTime startTime, LocalDateTime endTime) {
}
