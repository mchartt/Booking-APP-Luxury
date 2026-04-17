package com.smartdesk.backend.repository;

import com.smartdesk.backend.model.Desk;
import org.springframework.data.jpa.repository.JpaRepository;

public interface DeskRepository extends JpaRepository<Desk, Long> {
}
