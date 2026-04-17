package com.smartdesk.backend.model;

import jakarta.persistence.*;

import java.time.LocalDate;
import java.time.LocalDateTime;

@Entity
public class Booking {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long idBooking;

    private Long workerId;
    private Long deskId;
    private LocalDate bookedDay;
    private LocalDateTime startTime;
    private LocalDateTime endTime;

    public Long getIdBooking() { return idBooking; }
    public void setIdBooking(Long idBooking) { this.idBooking = idBooking; }
    public Long getWorkerId() { return workerId; }
    public void setWorkerId(Long workerId) { this.workerId = workerId; }
    public Long getDeskId() { return deskId; }
    public void setDeskId(Long deskId) { this.deskId = deskId; }
    public LocalDate getBookedDay() { return bookedDay; }
    public void setBookedDay(LocalDate bookedDay) { this.bookedDay = bookedDay; }
    public LocalDateTime getStartTime() { return startTime; }
    public void setStartTime(LocalDateTime startTime) { this.startTime = startTime; }
    public LocalDateTime getEndTime() { return endTime; }
    public void setEndTime(LocalDateTime endTime) { this.endTime = endTime; }
}
