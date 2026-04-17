package com.smartdesk.backend.model;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;

@Entity
public class Worker extends User {

    @Column(nullable = false)
    private String description;

    public String getDescription() { return description; }
    public void setDescription(String description) { this.description = description; }
}
