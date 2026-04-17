package com.smartdesk.backend.repository;

import com.smartdesk.backend.model.Host;
import org.springframework.data.jpa.repository.JpaRepository;

public interface HostRepository extends JpaRepository<Host, Long> {
}
