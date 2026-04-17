package com.smartdesk.backend.model;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;

@Entity
public class Technician extends User {

    @Column(nullable = false)
    private String specialisation;

    public String getSpecialisation() { return specialisation; }
    public void setSpecialisation(String specialisation) { this.specialisation = specialisation; }
}
